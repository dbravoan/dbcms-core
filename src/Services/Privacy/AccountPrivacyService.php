<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Privacy;

use DbCms\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

final class AccountPrivacyService
{
    /** @return array<string,mixed> */
    public function export(int $id): array
    {
        $user = User::query()->findOrFail($id);

        return [
            'account' => $user->toArray(),
            'roles' => $user->roles()->get(['roles.name', 'roles.slug'])->toArray(),
            'sessions' => DB::table('sessions')->where('user_id', $id)->get(['ip_address', 'user_agent', 'last_activity'])->map(static fn (object $row): array => (array) $row)->all(),
            'tokens' => Passport::token()->newQuery()->where('user_id', $id)->get(['name', 'scopes', 'revoked', 'created_at', 'expires_at'])->toArray(),
            'notifications' => DB::table('system_notifications')->where('user_id', $id)->get()->map(static fn (object $row): array => (array) $row)->all(),
            'media_migration_history' => DB::table('media_migration_runs')->where('user_id', $id)->get()->map(static fn (object $row): array => (array) $row)->all(),
            'integration_guide_progress' => DB::table('integration_guide_progress')->where('user_id', $id)->get(['module', 'guide_id', 'guide_version', 'step_id', 'completed_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'notification_preferences' => DB::table('notification_email_preferences')->where('user_id', $id)->get(['module', 'email_enabled'])->map(static fn (object $row): array => (array) $row)->all(),
            'notification_deliveries' => DB::table('notification_email_deliveries')->where('user_id', $id)->get(['notification_id', 'status', 'attempted_at', 'sent_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'security_history' => DB::table('capability_audit_logs')->where('user_id', $id)->get(['tool', 'ability', 'permission', 'status', 'args', 'error', 'ip', 'created_at'])->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }

    public function erase(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            if ($user->getAttribute('is_service_account')) {
                throw ValidationException::withMessages(['user' => 'Use the service-account lifecycle for a machine identity.']);
            }
            $user->delete();
        });
    }

    /** Called inside User::delete's transaction, before any tokens or related rows change. */
    public function assertDeletionAllowed(User $user): void
    {
        if (! $user->getAttribute('is_service_account') && $user->roles()->whereIn('slug', ['admin', 'super-admin'])->exists()) {
            $admins = User::query()->where('is_service_account', false)->whereHas('roles', static fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))->orderBy('id')->lockForUpdate()->pluck('id');
            if ($admins->count() < 2) {
                throw ValidationException::withMessages(['user' => 'Create another human administrator before deleting the last administrator.']);
            }
        }
        if (DB::table('media_migration_runs')->where('user_id', $user->id)->whereIn('status', ['pending', 'running'])->exists()) {
            throw ValidationException::withMessages(['user' => 'Finish or resolve the active media migration before deleting its operator account.']);
        }
        if (DB::table('notification_email_deliveries')->where('user_id', $user->id)->where('status', 'sending')->exists()) {
            throw ValidationException::withMessages(['user' => 'An email delivery is in progress or requires review. Resolve it before deleting this account.']);
        }
    }

    /** Remove credential and contact copies; retain only non-content security outcomes. */
    public function eraseOwnedCopies(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('system_notifications')->where('user_id', $user->id)->delete();
        DB::table('notification_email_deliveries')->where('user_id', $user->id)->delete();
        DB::table('media_migration_runs')->where('user_id', $user->id)->whereIn('status', ['completed', 'failed'])->update(['user_id' => null, 'options' => null, 'errors' => null, 'message' => null]);
        DB::table('capability_audit_logs')->where('user_id', $user->id)->update(['user_id' => null, 'token_id' => null, 'client_id' => null, 'args' => '[]', 'error' => null, 'ip' => null]);
        $tokens = Passport::token()->newQuery()->where('user_id', $user->id)->pluck('id')->all();
        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokens)->delete();
        Passport::token()->newQuery()->whereIn('id', $tokens)->delete();
        Passport::authCode()->newQuery()->where('user_id', $user->id)->delete();
        Passport::client()->newQuery()->where('owner_type', $user->getMorphClass())->where('owner_id', $user->id)->delete();
    }
}
