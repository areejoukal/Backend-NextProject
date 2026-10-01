<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class SendStepReminders extends Command
{
    protected $signature = 'notifications:step-reminders';
    protected $description = 'إرسال تذكيرات الخطوات للمستخدمين';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        // المستخدمين اللي عندهم خطوة نشطة + ما تفاعلوا منذ 3 أيام
        User::query()
            ->whereHas('nextSteps', fn ($q) => $q->whereIn('status', ['pending', 'in_progress']))
            ->chunk(100, function ($users) use ($notifications, &$sent) {
                foreach ($users as $user) {
                    $step = $user->nextSteps()
                        ->whereIn('status', ['pending', 'in_progress'])
                        ->latest()
                        ->first();

                    if (!$step) continue;

                    // إذا ما تفاعل منذ 3 أيام
                    if ($step->updated_at->diffInDays(now()) < 3) {
                        continue;
                    }

                    $created = $notifications->create(
                        $user,
                        'step_reminder',
                        'لديك خطوة تحتاج متابعة',
                        $step->title,
                        'dashboard.html'
                    );

                    if ($created) $sent++;
                }
            });

        $this->info("تم إرسال {$sent} تذكير.");
        return self::SUCCESS;
    }
}