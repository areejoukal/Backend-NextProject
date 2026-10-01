<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * إنشاء إشعار — مع منع التكرار.
     *
     * - لو نفس الإشعار (type + title + body) موجود خلال آخر 30 ثانية → لا يُنشأ جديد.
     * - هذا يمنع الإشعارات المكرّرة عند استدعاء الـ service مرتين في ثوانٍ.
     */
    public function create(
    User $user,
    string $type,
    string $title,
    string $body,
    ?string $url = null,
): ?Notification {
    // ⬇️ افحص التفضيلات
    if (!$this->isAllowed($user, $type)) {
        return null;  // لا تُنشئ إشعارًا
    }

    // منع التكرار
    $existing = $user->notifications()
        ->where('type', $type)
        ->where('title', $title)
        ->where('body', $body)
        ->where('created_at', '>=', now()->subSeconds(30))
        ->first();

    if ($existing) {
        return $existing;
    }

    return $user->notifications()->create([
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'is_read' => false,
    ]);
}

    /**
     * قائمة الإشعارات.
     */
    public function list(User $user, int $limit = 30): array
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * عدد غير المقروءة.
     */
    public function unreadCount(User $user): int
    {
        return $user->notifications()->where('is_read', false)->count();
    }

    /**
     * تعليم إشعار كمقروء.
     */
    public function markAsRead(User $user, int $id): bool
    {
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return false;
        }

        $notification->update(['is_read' => true]);
        return true;
    }

    /**
     * تعليم الكل كمقروء.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
 * فحص هل الإشعار مسموح حسب تفضيلات المستخدم.
 */
private function isAllowed(User $user, string $type): bool
{
    $settings = $user->settings;

    // لو لا توجد إعدادات، نسمح افتراضيًا
    if (!$settings) return true;

    // Mapping: نوع الإشعار → التفضيل المرتبط
    $preferenceMap = [
        'step_generated'         => 'step_reminders',
        'step_completed'         => 'step_reminders',
        'all_steps_completed'    => 'step_reminders',
        'reassessment_reminder'  => 'reassessment_reminders',
        'reassessment_started'   => 'reassessment_reminders',
        'change_summary'         => 'change_summary',
        'assessment_completed'   => null,  // دائمًا
        'info'                   => null,  // دائمًا
    ];

    // لو النوع ليس في القائمة، اسمح
    if (!array_key_exists($type, $preferenceMap)) {
        return true;
    }

    // لو التفضيل = null، اسمح دائمًا
    $preference = $preferenceMap[$type];
    if ($preference === null) return true;

    // افحص قيمة التفضيل
    return (bool) $settings->{$preference};
}
}

