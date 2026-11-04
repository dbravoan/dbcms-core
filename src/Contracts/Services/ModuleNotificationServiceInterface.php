<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Models\SystemNotification;
use Illuminate\Database\Eloquent\Collection;

interface ModuleNotificationServiceInterface
{
    /**
     * Dispatches a new notification to database and optional email.
     *
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
    ): SystemNotification;

    /**
     * Returns recent unread notifications.
     *
     * @return Collection<int, SystemNotification>
     */
    public function getUnreadNotifications(int $limit = 10): Collection;

    /**
     * Returns total unread notification count.
     */
    public function getUnreadCount(): int;

    /**
     * Marks a specific notification as read.
     */
    public function markAsRead(int|string $id): bool;

    /**
     * Marks all unread notifications as read.
     */
    public function markAllAsRead(): int;
}
