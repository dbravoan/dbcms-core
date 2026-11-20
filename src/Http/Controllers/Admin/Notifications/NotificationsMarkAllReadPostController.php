<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Notifications;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class NotificationsMarkAllReadPostController extends Controller
{
    public function __construct(
        private readonly ModuleNotificationServiceInterface $notificationService
    ) {}

    public function __invoke(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead();

        return response()->json([
            'success' => true,
            'marked_count' => $count,
            'unread_count' => 0,
        ]);
    }
}
