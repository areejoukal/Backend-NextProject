<?php

namespace App\Http\Controllers\Api;

use App\Models\Assessment;
use App\Services\Assessment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends ApiController
{
    public function __construct(
        private readonly AssessmentService $service,
    ) {}

    /**
     * بدء تقييم جديد.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $goal = $user->activeGoal;

        if (!$goal) {
            return $this->error('لا يوجد هدف نشط. حدّد هدفًا أولًا.', 422);
        }

        $assessment = $this->service->start($user, $goal);

        return $this->success([
            'message' => 'بدأ التقييم.',
            'assessment' => $this->assessmentResource($assessment),
        ], 201);
    }

    /**
     * جلب تقييم.
     */
    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        return $this->success([
            'assessment' => $this->assessmentResource($assessment, withAnswers: true),
        ]);
    }

    /**
     * السؤال التالي.
     */
    public function nextQuestion(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        if ($assessment->status !== 'in_progress') {
            return $this->error('التقييم غير نشط.', 422);
        }

        try {
            $next = $this->service->nextQuestion($assessment);
        } catch (\Throwable $e) {
            return $this->error('فشل جلب السؤال التالي: ' . $e->getMessage(), 500);
        }

        if ($next === null) {
            return $this->success([
                'question' => null,
                'is_complete' => true,
                'message' => 'وصلنا إلى فهم كافٍ. يمكنك إكمال التقييم.',
            ]);
        }

        return $this->success([
            'question' => $next['question'],
            'reason' => $next['reason'],
            'expected_info_gain' => $next['expected_info_gain'],
            'progress' => [
                'current' => $assessment->answers()->count() + 1,
                'max' => $assessment->max_steps,
            ],
            'meta' => $next['meta'],
            'is_complete' => false,
        ]);
    }


public function answer(Request $request, Assessment $assessment): JsonResponse
{
    $this->authorizeAssessment($request, $assessment);

    if ($assessment->status !== 'in_progress') {
        return $this->error('التقييم غير نشط.', 422);
    }

    $validated = $request->validate([
        'question_id'        => ['required', 'string'],
        'question_text'      => ['required', 'string'],
        'answer'             => ['required'],
        'reason'             => ['nullable', 'string', 'max:500'],
        'expected_info_gain' => ['nullable', 'numeric', 'min:0', 'max:1'],
    ]);

    try {
        $saved = $this->service->saveAnswer(
            $assessment,
            $validated['question_id'],
            $validated['answer'],
            $validated['reason'] ?? null,
            $validated['expected_info_gain'] ?? null,
            $validated['question_text'],
        );
    } catch (\Throwable $e) {
        return $this->error($e->getMessage(), 422);
    }

    return $this->success([
        'message' => 'تم حفظ الإجابة.',
        'answer' => [
            'id' => $saved->id,
            'question_id' => $saved->question_id,
            'order' => $saved->order,
        ],
        'progress' => [
            'current' => $assessment->fresh()->current_step,
            'max' => $assessment->max_steps,
        ],
    ], 201);

    
}


public function current(Request $request): JsonResponse
{
    $assessment = $request->user()
        ->assessments()
        ->where('status', 'in_progress')
        ->latest()
        ->first();

    if (!$assessment) {
        return $this->success([
            'assessment' => null,
            'has_assessment' => false,
        ]);
    }

    return $this->success([
        'assessment' => $this->assessmentResource($assessment),
        'has_assessment' => true,
    ]);
}



    /**
     * إكمال التقييم.
     */
    public function complete(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        if ($assessment->status !== 'in_progress') {
            return $this->error('التقييم مكتمل بالفعل أو غير نشط.', 422);
        }

        if ($assessment->answers()->count() < 3) {
            return $this->error('يجب الإجابة على 3 أسئلة على الأقل قبل الإكمال.', 422);
        }

        $assessment = $this->service->complete($assessment);

        return $this->success([
            'message' => 'اكتمل التقييم.',
            'assessment' => $this->assessmentResource($assessment),
        ]);
    }

    /**
     * إلغاء التقييم.
     */
    public function abandon(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        $assessment->update(['status' => 'abandoned']);

        return $this->success([
            'message' => 'تم إلغاء التقييم.',
        ]);
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function authorizeAssessment(Request $request, Assessment $assessment): void
    {
        if ($assessment->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح لك بالوصول إلى هذا التقييم.');
        }
    }

    private function assessmentResource(Assessment $assessment, bool $withAnswers = false): array
    {
        $data = [
            'id' => $assessment->id,
            'goal_id' => $assessment->goal_id,
            'type' => $assessment->type,
            'status' => $assessment->status,
            'current_step' => $assessment->current_step,
            'max_steps' => $assessment->max_steps,
            'answers_count' => $assessment->answers()->count(),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
            'created_at' => $assessment->created_at?->toIso8601String(),
        ];

        if ($withAnswers) {
            $data['answers'] = $assessment->answers()
                ->get()
                ->map(fn ($a) => [
                    'question_id' => $a->question_id,
                    'question_text' => $a->question_text,
                    'answer' => $a->answer,
                    'order' => $a->order,
                ])
                ->toArray();
        }

        return $data;
    }
}