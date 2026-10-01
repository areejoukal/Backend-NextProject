<?php

namespace App\Http\Controllers\Api;

use App\Models\Analysis;
use App\Models\Assessment;
use App\Services\Assessment\AnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends ApiController
{
    public function __construct(
        private readonly AnalysisService $service,
    ) {}

    /**
     * توليد تحليل لتقييم مكتمل.
     */
    public function generate(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        if ($assessment->status !== 'completed') {
            return $this->error('يجب إكمال التقييم أولًا.', 422);
        }

        try {
            $analysis = $this->service->generate($assessment);
        } catch (\Throwable $e) {
            return $this->error('فشل توليد التحليل: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'message' => 'تم توليد التحليل.',
            'analysis' => $this->analysisResource($analysis),
        ], 201);
    }

    /**
     * جلب تحليل محدد.
     */
    public function show(Request $request, Analysis $analysis): JsonResponse
    {
        $this->authorizeAnalysis($request, $analysis);

        return $this->success([
            'analysis' => $this->analysisResource($analysis),
        ]);
    }

    /**
     * جلب تحليل تقييم.
     */
    public function forAssessment(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($request, $assessment);

        $analysis = $assessment->analysis;

        if (!$analysis) {
            return $this->success([
                'analysis' => null,
                'has_analysis' => false,
            ]);
        }

        return $this->success([
            'analysis' => $this->analysisResource($analysis),
            'has_analysis' => true,
        ]);
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function authorizeAssessment(Request $request, Assessment $assessment): void
    {
        if ($assessment->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح.');
        }
    }

    private function authorizeAnalysis(Request $request, Analysis $analysis): void
    {
        if ($analysis->assessment->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح.');
        }
    }

    private function analysisResource(Analysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'assessment_id' => $analysis->assessment_id,
            'headline' => $analysis->headline,
            'summary' => $analysis->summary,
            'known' => $analysis->known ?? [],
            'unknown' => $analysis->unknown ?? [],
            'controllable' => $analysis->controllable ?? [],
            'uncontrollable' => $analysis->uncontrollable ?? [],
            'ai_meta' => [
                'model' => $analysis->ai_model,
                'tokens' => $analysis->ai_tokens,
                'latency_ms' => $analysis->ai_latency_ms,
            ],
            'created_at' => $analysis->created_at?->toIso8601String(),
        ];
    }
}