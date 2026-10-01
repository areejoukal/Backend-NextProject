<?php

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\Goal;
use App\Models\User;
use App\Services\AI\Prompts\ReassessmentPrompt;
use Illuminate\Support\Facades\DB;

class ReassessmentService
{
    public function __construct(
        private readonly ReassessmentPrompt $reassessmentPrompt,
    ) {}

    /**
     * بدء إعادة تقييم.
     */
    public function start(User $user): Assessment
    {
        $goal = $user->activeGoal;

        if (!$goal) {
            throw new \RuntimeException('لا يوجد هدف نشط.');
        }

        // جلب آخر تقييم مكتمل
        $previousAssessment = $user->assessments()
            ->where('status', 'completed')
            ->where('type', 'initial')
            ->latest()
            ->first();

        if (!$previousAssessment) {
            throw new \RuntimeException('لا يوجد تقييم سابق. ابدأ بتقييم عادي أولًا.');
        }

        return DB::transaction(function () use ($user, $goal, $previousAssessment) {
            // إلغاء أي تقييم قيد التنفيذ
            $user->assessments()
                ->where('status', 'in_progress')
                ->update(['status' => 'abandoned']);

            // جلب الإجابات السابقة
            $previousAnswers = $previousAssessment->answers()
                ->get()
                ->map(fn ($a) => [
                    'question_id' => $a->question_id,
                    'question_text' => $a->question_text,
                    'answer' => is_array($a->answer) ? ($a->answer['value'] ?? null) : $a->answer,
                ])
                ->toArray();

            // بناء userState
            $userState = $this->buildUserState($user, $goal);

$result = $this->reassessmentPrompt
    ->withUserState($userState)
    ->withPreviousAnswers($previousAnswers)
    ->generate();

// ✅ data مباشرة (array)
$data = $result->json;

if (!is_array($data)) {
    \Log::warning('[Reassessment] AI returned non-array', [
        'data' => $data,
    ]);
    throw new \RuntimeException('فشل تحليل الاستجابة من AI.');
}
            if (!is_array($data)) {
                \Log::warning('[Reassessment] AI returned non-array', [
                    'content' => $result->content,
                ]);
                throw new \RuntimeException('فشل تحليل الاستجابة من AI.');
            }

            // ✅ استخرج الأسئلة (بدل question_id → text)
            $suggestedQuestions = $data['questions'] ?? [];

            // إنشاء تقييم جديد من نوع reassessment
            $assessment = Assessment::create([
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'type' => 'reassessment',
                'status' => 'in_progress',
                'current_step' => 1,
                'max_steps' => 4,   // ← ✅ رقم ثابت (2-4 أسئلة)
                'current_question_id' => null,
                'user_state' => array_merge($userState, [
                    'reassessment_context' => [
                        'changed' => $data['changed'] ?? [],
                        'preserved' => $data['preserved'] ?? [],
                        'summary' => $data['summary'] ?? null,
                        // ✅ نحفظ الأسئلة الكاملة (نص + خيارات) بدل ID فقط
                        'suggested_questions' => array_map(fn ($q) => [
                            'id' => $q['id'] ?? 'q_reassess_' . uniqid(),
                            'text' => $q['text'] ?? '',
                            'type' => $q['type'] ?? 'single_choice',
                            'options' => $q['options'] ?? [],
                            'reason' => $q['reason'] ?? null,
                        ], $suggestedQuestions),
                    ],
                ]),
            ]);

            // تسجيل الحدث
            $user->progressEvents()->create([
                'event' => 'reassessment_started',
                'metadata' => [
                    'assessment_id' => $assessment->id,
                    'previous_assessment_id' => $previousAssessment->id,
                ],
            ]);

            return $assessment;
        });
    }

    /**
     * بناء userState.
     */
    private function buildUserState(User $user, Goal $goal): array
    {
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