<?php

namespace App\Services\Assessment;

use App\Models\Analysis;
use App\Models\NextStep;
use App\Services\AI\Prompts\NextStepPrompt;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class NextStepService
{
    public function __construct(
        private readonly NextStepPrompt $nextStepPrompt,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * توليد خطوات من تحليل.
     *
     * @return NextStep[]
     */
    public function generate(Analysis $analysis): array
    {
        $assessment = $analysis->assessment;

        // حذف أي خطوات سابقة (نعيد التوليد)
        $analysis->nextSteps()->delete();

        // بناء userState
        $userState = $assessment->user_state ?? $this->buildUserState($assessment);

        // تجهيز بيانات التحليل
        $analysisData = [
            'headline' => $analysis->headline,
            'summary' => $analysis->summary,
            'known' => $analysis->known ?? [],
            'unknown' => $analysis->unknown ?? [],
            'controllable' => $analysis->controllable ?? [],
            'uncontrollable' => $analysis->uncontrollable ?? [],
        ];

        // توليد الخطوات عبر AI
        $result = $this->nextStepPrompt
            ->withUserState($userState)
            ->withAnalysis($analysisData)
            ->generate();

        $data = $result->json;

        // حفظ في قاعدة البيانات
        return DB::transaction(function () use ($analysis, $data) {
            $steps = [];

            // Primary Step
            $primary = $data['primary'];
            $steps[] = NextStep::create([
                'user_id' => $analysis->assessment->user_id,
                'analysis_id' => $analysis->id,
                'order' => 1,
                'role' => 'primary',
                'title' => $primary['title'],
                'reason' => $primary['reason'] ?? null,
                'why' => $primary['why'] ?? null,
                'outcome' => $primary['outcome'] ?? null,
                'steps' => $primary['steps'] ?? [],
                'completion_criteria' => $primary['completion_criteria'] ?? null,
                'effort' => $primary['effort'] ?? 'medium',
                'time_estimate' => $primary['time'] ?? null,
                'status' => 'pending',
            ]);

            // Secondary Steps
            foreach ($data['secondary'] ?? [] as $i => $sec) {
                $steps[] = NextStep::create([
                    'user_id' => $analysis->assessment->user_id,
                    'analysis_id' => $analysis->id,
                    'order' => $i + 2,
                    'role' => 'secondary',
                    'title' => $sec['title'],
                    'reason' => $sec['text'] ?? null,
                    'status' => 'pending',
                ]);
            }

            // ⬇️ إشعار: خطوات جديدة جاهزة
            // ⬇️ إشعار: خطوات جديدة جاهزة
            $primaryStep = collect($steps)->firstWhere('role', 'primary');
            if ($primaryStep) {
                $this->notifications->create(
                    $analysis->assessment->user,
                    'step_generated',
                    'خطوتك الجديدة جاهزة 🎯',
                    $primaryStep->title,
                    'next-steps.html'
                );
            }

            return $steps;
        });
    }

    /**
     * بناء userState من التقييم (لو لم يُخزّن).
     */
    private function buildUserState($assessment): array
    {
        $user = $assessment->user;
        $goal = $assessment->goal;
        $profile = $user->profile;

        return [
            'goal' => [
                'type' => $goal->type,
                'title' => $goal->title,
                'details' => $goal->details ?? [],
            ],
            'profile' => [
                'age' => $profile?->age,
                'location' => $profile?->location,
                'education' => $profile?->education,
                'field' => $profile?->field,
                'experience' => $profile?->experience,
                'background' => $profile?->background,
            ],
        ];
    }
}