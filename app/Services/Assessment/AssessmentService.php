<?php

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\Goal;
use App\Models\User;
use App\Services\AI\Prompts\AdaptiveQuestionPrompt;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentService
{
    private const MIN_QUESTIONS = 3;

    public function __construct(
        private readonly AdaptiveQuestionPrompt $adaptivePrompt,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * بدء تقييم جديد.
     */
    public function start(User $user, Goal $goal): Assessment
    {
        return DB::transaction(function () use ($user, $goal) {
            $user->assessments()
                ->where('status', 'in_progress')
                ->update(['status' => 'abandoned']);

            return Assessment::create([
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'type' => 'initial',
                'status' => 'in_progress',
                'current_step' => 1,
                'max_steps' => 6,
                'current_question_id' => null,
                'user_state' => $this->buildUserState($user, $goal),
            ]);
        });
    }

    /**
     * جلب السؤال التالي.
     */
    public function nextQuestion(Assessment $assessment): ?array
    {
        if ($assessment->status !== 'in_progress') {
            throw new \RuntimeException('التقييم غير نشط.');
        }

        $answersCount = $assessment->answers()->count();

        // الحد الأقصى
        if ($answersCount >= $assessment->max_steps) {
            Log::info('[Assessment] Max steps reached', [
                'answers_count' => $answersCount,
                'max_steps' => $assessment->max_steps,
            ]);

            return null;
        }

        // Reassessment
        if ($assessment->type === 'reassessment') {
            return $this->nextReassessmentQuestion(
                $assessment,
                $answersCount
            );
        }

        // Initial assessment
        return $this->nextInitialQuestion(
            $assessment,
            $answersCount
        );
    }

    /**
     * توليد السؤال من AI.
     */
    private function nextInitialQuestion(
        Assessment $assessment,
        int $answersCount
    ): ?array {
        $userState = $assessment->user_state
            ?? $this->buildUserState(
                $assessment->user,
                $assessment->goal
            );

        $previousAnswers = $assessment
            ->answers()
            ->get()
            ->map(fn ($a) => [
                'question_id' => $a->question_id,
                'question_text' => $a->question_text,
                'answer' => $a->answer,
            ])
            ->toArray();

        /*
         * نحاول توليد السؤال.
         *
         * إذا حاول AI إنهاء التقييم قبل الحد الأدنى،
         * سنعيد الطلب مرة واحدة مع نفس السياق.
         */
        for ($attempt = 1; $attempt <= 2; $attempt++) {

            $result = $this->adaptivePrompt
                ->withUserState($userState)
                ->withPreviousAnswers($previousAnswers)
                ->withProgress(
                    $answersCount + 1,
                    $assessment->max_steps
                )
                ->forceContinue($answersCount < self::MIN_QUESTIONS)
                ->generate();

            $data = $result->json;

            if (!is_array($data)) {
                Log::warning(
                    '[Assessment] AI returned non-array JSON',
                    [
                        'attempt' => $attempt,
                        'content' => $result->content,
                        'raw' => $result->raw,
                    ]
                );

                if ($attempt < 2) {
                    continue;
                }

                throw new \RuntimeException(
                    'تعذر قراءة استجابة الذكاء الاصطناعي. يرجى المحاولة مرة أخرى.'
                );
            }

            /*
             * إذا حاول AI التوقف قبل 3 إجابات،
             * لا نسمح بذلك.
             *
             * نعيد المحاولة للحصول على سؤال.
             */
            if (
                !empty($data['should_stop'])
                && $answersCount < self::MIN_QUESTIONS
            ) {
                Log::warning(
                    '[Assessment] AI tried to stop before minimum questions',
                    [
                        'attempt' => $attempt,
                        'answers_count' => $answersCount,
                        'minimum' => self::MIN_QUESTIONS,
                        'reason' => $data['reason'] ?? null,
                    ]
                );

                if ($attempt < 2) {
                    continue;
                }

                /*
                 * إذا فشل AI مرتين، لا ننهي التقييم.
                 * لا نعيد null لأن null سيجعل الواجهة تستدعي complete.
                 */
                Log::error(
                    '[Assessment] AI failed to generate mandatory question',
                    [
                        'answers_count' => $answersCount,
                        'data' => $data,
                    ]
                );

                throw new \RuntimeException(
                    'تعذر توليد السؤال التالي. يرجى المحاولة مرة أخرى.'
                );
            }

            /*
             * السماح بالتوقف فقط بعد الحد الأدنى.
             */
            if (
                !empty($data['should_stop'])
                && $answersCount >= self::MIN_QUESTIONS
            ) {
                Log::info('[Assessment] AI decided to stop', [
                    'answers_count' => $answersCount,
                    'reason' => $data['reason'] ?? null,
                ]);

                return null;
            }

            /*
             * يجب أن يحتوي الرد على سؤال.
             */
            $question = $data['question'] ?? null;

            if (!$question || !is_array($question)) {
                Log::warning(
                    '[Assessment] AI returned no valid question',
                    [
                        'attempt' => $attempt,
                        'data' => $data,
                    ]
                );

                if ($attempt < 2) {
                    continue;
                }

                throw new \RuntimeException(
                    'تعذر توليد سؤال صالح. يرجى المحاولة مرة أخرى.'
                );
            }

            /*
             * استخراج نص السؤال.
             */
            $text = $question['text']
                ?? $question['question']
                ?? $question['title']
                ?? $question['prompt']
                ?? null;

            if (empty($text)) {
                Log::warning(
                    '[Assessment] AI question missing text',
                    [
                        'question' => $question,
                    ]
                );

                if ($attempt < 2) {
                    continue;
                }

                throw new \RuntimeException(
                    'السؤال الذي تم توليده غير صالح.'
                );
            }

            /*
             * ضمان الحقول الأساسية.
             */
            $question['id'] = $question['id']
                ?? 'q_gen_' . uniqid();

            $question['type'] = $question['type']
                ?? 'single_choice';

            $question['text'] = $text;

            $question['options'] = $question['options']
                ?? [];

            /*
             * حفظ السؤال الحالي.
             */
            $assessment->update([
                'current_question_id' => $question['id'],
            ]);

            Log::info('[Assessment] AI question received', [
                'attempt' => $attempt,
                'answers_count' => $answersCount,
                'question' => $question,
            ]);

            return [
                'question' => $question,

                'reason' =>
                    $data['reason']
                    ?? $question['reason']
                    ?? null,

                'expected_info_gain' =>
                    $question['expected_info_gain']
                    ?? null,

                'meta' => [
                    'tokens' => $result->totalTokens,
                    'latency_ms' => $result->latencyMs,
                    'model' => $result->model,
                ],
            ];
        }

        return null;
    }

    /**
     * سؤال Reassessment.
     */
    private function nextReassessmentQuestion(
        Assessment $assessment,
        int $answersCount
    ): ?array {
        $context =
            $assessment->user_state['reassessment_context']
            ?? [];

        $suggested =
            $context['suggested_questions']
            ?? [];

        if (!isset($suggested[$answersCount])) {
            Log::info(
                '[Reassessment] All suggested questions exhausted',
                [
                    'answers_count' => $answersCount,
                    'total_suggested' => count($suggested),
                ]
            );

            return null;
        }

        $question = $suggested[$answersCount];

        $question['id'] =
            $question['id']
            ?? 'q_reassess_' . uniqid();

        $question['type'] =
            $question['type']
            ?? 'single_choice';

        $question['options'] =
            $question['options']
            ?? [];

        $assessment->update([
            'current_question_id' => $question['id'],
        ]);

        return [
            'question' => $question,
            'reason' => $question['reason'] ?? null,
            'expected_info_gain' =>
                $question['expected_info_gain'] ?? null,

            'meta' => [
                'source' => 'reassessment_precomputed',
            ],
        ];
    }

    /**
     * حفظ إجابة.
     */
    public function saveAnswer(
        Assessment $assessment,
        string $questionId,
        mixed $answer,
        ?string $reason = null,
        ?float $infoGain = null,
        ?string $questionText = null,
    ): AssessmentAnswer {
        if ($assessment->current_question_id === null) {
            throw new \RuntimeException('لا يوجد سؤال حالي.');
        }

        if ($assessment->current_question_id !== $questionId) {
            throw new \RuntimeException(
                'السؤال المرسل لا يطابق السؤال الحالي.'
            );
        }

        $nextOrder =
            ($assessment->answers()->max('order') ?? 0) + 1;

        $answerModel = AssessmentAnswer::updateOrCreate(
            [
                'assessment_id' => $assessment->id,
                'question_id' => $questionId,
            ],
            [
                'question_text' =>
                    $questionText ?? $questionId,

                'answer' => $answer,

                'reason' => $reason,

                'info_gain' => $infoGain,

                'order' => $nextOrder,
            ]
        );

        $assessment->update([
            'current_step' =>
                $assessment->answers()->count(),

            'current_question_id' => null,
        ]);

        return $answerModel;
    }

    /**
     * إكمال التقييم.
     */
    public function complete(Assessment $assessment): Assessment
    {
        $answersCount = $assessment->answers()->count();

        // حماية Backend نهائية.
        if ($answersCount < self::MIN_QUESTIONS) {
            throw new \RuntimeException(
                'يجب الإجابة على 3 أسئلة على الأقل قبل الإكمال.'
            );
        }

        $assessment->update([
            'status' => 'completed',
            'current_question_id' => null,
            'completed_at' => now(),
        ]);

        $this->notifications->create(
            $assessment->user,
            'assessment_completed',
            'اكتمل تقييمك ✓',
            "أجبت على {$answersCount} أسئلة. جارٍ تحضير صورتك الحالية.",
            'assessment-processing.html'
        );

        return $assessment->fresh();
    }

    /**
     * بناء حالة المستخدم.
     */
    private function buildUserState(
        User $user,
        Goal $goal
    ): array {
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