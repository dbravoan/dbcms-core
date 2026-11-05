<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Notifications;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class NotificationReadPostController extends Controller
{
    public function __construct(
        private readonly ModuleNotificationServiceInterface $notificationService
    ) {}

    public function __invoke(int|string $id): JsonResponse
    {
        $success = $this->notificationService->markAsRead($id);

        return response()->json([
            'success' => $success,
            'unread_count' => $this->notificationService->getUnreadCount(),
        ]);
    }
}
