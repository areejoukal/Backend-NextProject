<?php

namespace App\Services\Assessment;

use App\Models\Analysis;
use App\Models\Assessment;
use App\Services\AI\Prompts\AnalysisPrompt;

class AnalysisService
{
    public function __construct(
        private readonly AnalysisPrompt $analysisPrompt,
    ) {}

    /**
     * توليد تحليل من تقييم مكتمل.
     */
    public function generate(Assessment $assessment): Analysis
    {
        // منع التكرار
        if ($assessment->analysis) {
            return $assessment->analysis;
        }

        if ($assessment->status !== 'completed') {
            throw new \RuntimeException(
                'لا يمكن التحليل قبل إكمال التقييم.'
            );
        }

        if ($assessment->answers()->count() < 3) {
            throw new \RuntimeException(
                'يجب الإجابة على 3 أسئلة على الأقل.'
            );
        }

        // جلب السياق
        $userState = $assessment->user_state
            ?? $this->buildUserState($assessment);

        $answers = $assessment->answers()
            ->get()
            ->map(fn ($a) => [
                'question_id' => $a->question_id,
                'question_text' => $a->question_text,
                'answer' => is_array($a->answer)
                    ? ($a->answer['value'] ?? $a->answer)
                    : $a->answer,
            ])
            ->toArray();

        // توليد التحليل
        $result = $this->analysisPrompt
            ->withUserState($userState)
            ->withAnswers($answers)
            ->generate();

        // PromptResult يحتوي البيانات في data
        $data = $result->data;

        if (!is_array($data)) {
            throw new \RuntimeException(
                'استجابة التحليل غير صالحة.'
            );
        }

        // التأكد من وجود الحقول الأساسية
        foreach ([
            'headline',
            'summary',
            'known',
            'unknown',
            'controllable',
            'uncontrollable',
        ] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \RuntimeException(
                    "استجابة التحليل تفتقد الحقل: {$key}"
                );
            }
        }

        // حفظ التحليل
        return Analysis::create([
            'assessment_id' => $assessment->id,

            'headline' => $data['headline'],

            'summary' => $data['summary'],

            'known' => $data['known'] ?? [],

            'unknown' => $data['unknown'] ?? [],

            'controllable' => $data['controllable'] ?? [],

            'uncontrollable' => $data['uncontrollable'] ?? [],

            // PromptResult -> raw -> AIResponse
            'ai_model' => $result->model(),

            'ai_tokens' => $result->tokens(),

            'ai_latency_ms' => $result->latencyMs(),
        ]);
    }

    /**
     * بناء userState من التقييم
     * لو لم يكن مخزنًا داخل assessment.
     */
    private function buildUserState(Assessment $assessment): array
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