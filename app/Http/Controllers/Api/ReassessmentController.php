<?php

namespace App\Http\Controllers\Api;

use App\Models\Assessment;
use App\Services\Assessment\ReassessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReassessmentController extends ApiController
{
    public function __construct(
        private readonly ReassessmentService $service,
    ) {}

    /**
     * بدء إعادة تقييم.
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $assessment = $this->service->start($request->user());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'message' => 'بدأت إعادة التقييم.',
            'assessment' => [
                'id' => $assessment->id,
                'type' => $assessment->type,
                'status' => $assessment->status,
                'max_steps' => $assessment->max_steps,
                'context' => $assessment->user_state['reassessment_context'] ?? null,
            ],
        ], 201);
    }
}