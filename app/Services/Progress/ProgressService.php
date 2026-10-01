<?php

namespace App\Services\Progress;

use App\Models\NextStep;
use App\Models\User;

class ProgressService
{
    /**
     * ملخص التقدم للمستخدم.
     */
    public function summary(User $user): array
    {
        // كل خطوات المستخدم من التحليل الأخير
        $latestAnalysis = $user->assessments()
            ->whereHas('analysis')
            ->latest()
            ->first()?->analysis;

        $totalSteps = 0;
        $completedSteps = 0;

        if ($latestAnalysis) {
            $totalSteps = $latestAnalysis->nextSteps()->count();
            $completedSteps = $latestAnalysis->nextSteps()
                ->where('status', 'completed')
                ->count();
        }

        $percentage = $totalSteps > 0
            ? (int) round(($completedSteps / $totalSteps) * 100)
            : 0;

        // الخطوة الحالية
        $currentStep = $user->nextSteps()
            ->where('role', 'primary')
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();

        // آخر تحديث
        $lastEvent = $user->progressEvents()->latest()->first();

        return [
            'completed_count' => $completedSteps,
            'total_count' => $totalSteps,
            'percentage' => $percentage,
            'current_step' => $currentStep ? [
                'id' => $currentStep->id,
                'title' => $currentStep->title,
                'status' => $currentStep->status,
            ] : null,
            'last_updated_at' => $lastEvent?->created_at?->toIso8601String()
                ?? $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * سجل الأحداث.
     */
    public function events(User $user, int $limit = 20): array
    {
        return $user->progressEvents()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event' => $e->event,
                'label' => $this->eventLabel($e->event),
                'metadata' => $e->metadata ?? [],
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'step_completed' => 'أنجزت خطوة',
            'reassessment_started' => 'بدأت إعادة تقييم',
            'reassessment_completed' => 'أكملت إعادة تقييم',
            'goal_created' => 'حددت هدفًا',
            'goal_changed' => 'غيّرت هدفك',
            'assessment_completed' => 'أكملت التقييم',
            default => $event,
        };
    }
}