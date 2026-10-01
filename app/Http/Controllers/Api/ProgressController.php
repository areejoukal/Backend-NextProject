<?php

namespace App\Http\Controllers\Api;

use App\Services\Progress\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends ApiController
{
    public function __construct(
        private readonly ProgressService $service,
    ) {}

    /**
     * ملخص التقدم.
     */
    public function summary(Request $request): JsonResponse
    {
        return $this->success([
            'progress' => $this->service->summary($request->user()),
        ]);
    }

    /**
     * سجل الأحداث.
     */
    public function events(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $limit = max(1, min(100, $limit));

        return $this->success([
            'events' => $this->service->events($request->user(), $limit),
        ]);
    }
}