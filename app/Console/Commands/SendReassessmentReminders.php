<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class SendReassessmentReminders extends Command
{
    protected $signature = 'notifications:reassessment-reminders';
    protected $description = 'تذكير المستخدمين بإعادة التقييم';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        User::query()
            ->whereHas('assessments', fn ($q) => $q->where('status', 'completed'))
            ->chunk(100, function ($users) use ($notifications, &$sent) {
                foreach ($users as $user) {
                    $lastAssessment = $user->assessments()
                        ->where('status', 'completed')
                        ->latest()
                        ->first();

                    if (!$lastAssessment) continue;

                    // إذا مر 60 يوم على آخر تقييم
                    if ($lastAssessment->completed_at->diffInDays(now()) < 60) {
                        continue;
                    }

                    // إذا ما عنده تقييم قيد التنفيذ
                    $inProgress = $user->assessments()
                        ->where('status', 'in_progress')
                        ->exists();

                    if ($inProgress) continue;

                    $created = $notifications->create(
                        $user,
                        'reassessment_reminder',
                        'حان وقت مراجعة وضعك',
                        'مرّ شهران على تقييمك. لنحدّث خطتك.',
                        'reassessment.html'
                    );

                    if ($created) $sent++;
                }
            });

        $this->info("تم إرسال {$sent} تذكير.");
        return self::SUCCESS;
    }
}