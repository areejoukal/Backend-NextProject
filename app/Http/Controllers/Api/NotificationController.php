<?php

namespace App\Http\Controllers\Api;

use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    /**
     * قائمة الإشعارات.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 30);
        $limit = max(1, min(100, $limit));

        return $this->success([
            'notifications' => $this->service->list($user, $limit),
            'unread_count' => $this->service->unreadCount($user),
        ]);
    }

    /**
     * تعليم إشعار كمقروء.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $ok = $this->service->markAsRead($request->user(), $id);

        if (!$ok) {
            return $this->error('الإشعار غير موجود.', 404);
        }

        return $this->success([
            'message' => 'تم تعليم الإشعار كمقروء.',
            'unread_count' => $this->service->unreadCount($request->user()),
        ]);
    }

    /**
     * تعليم الكل كمقروء.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->service->markAllAsRead($request->user());

        return $this->success([
            'message' => "تم تعليم {$count} إشعارًا كمقروء.",
            'unread_count' => 0,
        ]);
    }
}