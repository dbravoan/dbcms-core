<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Notifications;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Contracts\Services\NotificationRecipientResolverInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationsGetController extends Controller
{
    public function __construct(
        private readonly ModuleNotificationServiceInterface $notificationService,
        private readonly NotificationRecipientResolverInterface $recipients,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json([
            'unread_count' => $this->notificationService->getUnreadCount(),
            'notifications' => $this->notificationService->getUnreadNotifications(15),
            'email_enabled' => $this->recipients->emailEnabled($user),
        ]);
    }
}
