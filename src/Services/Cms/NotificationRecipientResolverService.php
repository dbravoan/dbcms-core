<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\NotificationRecipientResolverInterface;
use DbCms\Core\Models\NotificationEmailPreference;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Notifications\NotificationRecipientPolicyRegistry;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NotificationRecipientResolverService implements NotificationRecipientResolverInterface
{
    public function __construct(private readonly ModuleManager $modules, private readonly NotificationRecipientPolicyRegistry $policies) {}

    public function recipients(SystemNotification $notification): Collection
    {
        if ($notification->user_id === null && ! $notification->is_global) {
            return collect();
        }
        $query = User::query()->where('is_service_account', false)->with('roles.permissions');
        if ($notification->user_id !== null) {
            $query->whereKey($notification->user_id);
        } else {
            $roles = $this->broadcastRoles();
            if ($roles === []) {
                return collect();
            }
            $query->whereHas('roles', fn ($query) => $query->whereIn('slug', $roles));
        }

        return $query->get()->filter(fn (User $user): bool => $this->allows($notification, $user));
    }

    public function allows(SystemNotification $notification, User $user): bool
    {
        if (! app(LegalPageService::class)->canCollect('core')) {
            return false;
        }
        if ($notification->user_id === null && ! $notification->is_global) {
            return false;
        }
        if (! (bool) config('module_notifications.email_enabled', true) || (bool) $user->getAttribute('is_service_account') || ! filter_var($user->email, FILTER_VALIDATE_EMAIL) || ! $user->hasPermission('access:admin') || ! $this->emailEnabled($user, $notification->module)) {
            return false;
        }
        if ($notification->user_id !== null && $notification->user_id !== $user->id) {
            return false;
        }
        if ($notification->user_id === null) {
            $roles = $this->broadcastRoles();
            if ($roles === [] || ! $user->hasRole($roles)) {
                return false;
            }
        }
        if (! $this->modules->isModuleEnabled($notification->module)) {
            return false;
        }
        $permission = $notification->data['required_permission'] ?? config('module_notifications.module_permissions.'.$notification->module);
        if (is_string($permission) && ! $user->hasPermission($permission)) {
            return false;
        }

        return $this->policies->allows($notification, $user);
    }

    public function setPreference(User $user, string $module, bool $enabled): void
    {
        if ($module !== '*' && ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $module)) {
            throw ValidationException::withMessages(['module' => 'Invalid notification module.']);
        }
        DB::transaction(fn () => NotificationEmailPreference::updateOrCreate(['user_id' => $user->id, 'module' => $module], ['email_enabled' => $enabled]));
    }

    public function emailEnabled(User $user, string $module = '*'): bool
    {
        $preferences = NotificationEmailPreference::where('user_id', $user->id)->whereIn('module', ['*', $module])->pluck('email_enabled', 'module');

        return (bool) ($preferences['*'] ?? true) && (bool) ($preferences[$module] ?? true);
    }

    /** @return list<string> */
    private function broadcastRoles(): array
    {
        $roles = config('module_notifications.broadcast_roles', ['admin', 'super-admin']);

        return is_array($roles) ? array_values(array_filter($roles, is_string(...))) : [];
    }
}
