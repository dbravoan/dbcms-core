<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Contracts\Services\NotificationRecipientResolverInterface;
use DbCms\Core\Jobs\SendModuleNotificationEmailJob;
use DbCms\Core\Models\NotificationEmailDelivery;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModuleNotificationService implements ModuleNotificationServiceInterface
{
    public function __construct(private readonly NotificationRecipientResolverInterface $recipients) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        string $module,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = [],
        bool $sendEmail = true,
        ?int $userId = null,
        bool $inApp = true,
    ): SystemNotification {
        app(LegalPageService::class)->requireProcessing('core');

        return DB::transaction(function () use ($module, $type, $title, $message, $actionUrl, $data, $sendEmail, $userId, $inApp): SystemNotification {
            if ($userId !== null) {
                User::query()->findOrFail($userId);
            }
            $notification = SystemNotification::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'module' => $module,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'data' => $data,
                'is_read' => false,
                'is_visible' => $inApp,
                'is_global' => $userId === null,
            ]);

            if ($sendEmail) {
                $this->dispatchEmailNotification($notification);
            }

            return $notification;
        });
    }

    /**
     * @return Collection<int, SystemNotification>
     */
    public function getUnreadNotifications(int $limit = 10): Collection
    {
        return $this->visibleQuery()->unread()
            ->forUser($this->currentUserId())
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUnreadCount(): int
    {
        return $this->visibleQuery()->unread()
            ->forUser($this->currentUserId())
            ->count();
    }

    public function markAsRead(int|string $id): bool
    {
        $query = $this->visibleQuery()->where('is_visible', true)->forUser($this->currentUserId());

        if (is_numeric($id)) {
            $notification = $query->find((int) $id);
        } else {
            /** @var SystemNotification|null $notification */
            $notification = $query->where('uuid', $id)->first();
        }

        if (! $notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    public function markAllAsRead(): int
    {
        return $this->visibleQuery()->unread()
            ->forUser($this->currentUserId())
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /** @return Builder<SystemNotification> */
    private function visibleQuery(): Builder
    {
        $query = SystemNotification::query();

        return $query;
    }

    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    protected function dispatchEmailNotification(SystemNotification $notification): void
    {
        foreach ($this->recipients->recipients($notification) as $recipient) {
            $delivery = NotificationEmailDelivery::create(['notification_id' => $notification->id, 'user_id' => $recipient->id, 'status' => 'pending']);
            DB::afterCommit(function () use ($delivery): void {
                try {
                    SendModuleNotificationEmailJob::dispatch($delivery->id)->afterCommit();
                } catch (\Throwable $error) {
                    NotificationEmailDelivery::whereKey($delivery->id)->where('status', 'pending')->update(['status' => 'failed', 'failure_code' => $error::class]);
                    Log::warning('Notification dispatch failed after commit', ['delivery_id' => $delivery->id, 'exception' => $error::class]);
                }
            });
        }
    }
}
