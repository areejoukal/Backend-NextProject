<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoalController extends ApiController
{
    /**
     * الهدف النشط الحالي.
     */
    public function active(Request $request): JsonResponse
    {
        $goal = $request->user()->activeGoal;

        if (!$goal) {
            return $this->success([
                'goal' => null,
                'has_goal' => false,
            ]);
        }

        return $this->success([
            'goal' => $this->goalResource($goal),
            'has_goal' => true,
        ]);
    }

    /**
     * كل الأهداف (مختصرة).
     */
    public function index(Request $request): JsonResponse
    {
        $goals = $request->user()
            ->goals()
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'goals' => $goals->map(fn ($g) => $this->goalResource($g))->toArray(),
        ]);
    }

    /**
     * إنشاء هدف جديد.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $user = $request->user();

        $goal = DB::transaction(function () use ($user, $request) {
            // إلغاء تفعيل الأهداف القديمة
            $user->goals()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // إنشاء الهدف الجديد
            return $user->goals()->create([
                'type' => $request->validated('type'),
                'title' => $request->validated('title'),
                'details' => $request->validated('details', []),
                'is_active' => true,
            ]);
        });

        return $this->success([
            'message' => 'تم حفظ الهدف.',
            'goal' => $this->goalResource($goal),
        ], 201);
    }

    /**
     * عرض هدف.
     */
    public function show(Request $request, Goal $goal): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        return $this->success([
            'goal' => $this->goalResource($goal),
        ]);
    }

    /**
     * تحديث هدف.
     */
    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $goal->update($request->validated());

        return $this->success([
            'message' => 'تم تحديث الهدف.',
            'goal' => $this->goalResource($goal->fresh()),
        ]);
    }

    /**
     * حذف هدف.
     */
    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $goal->delete();

        return $this->success([
            'message' => 'تم حذف الهدف.',
        ]);
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function authorizeGoal(Request $request, Goal $goal): void
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح لك بالوصول إلى هذا الهدف.');
        }
    }

    private function goalResource(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'type' => $goal->type,
            'title' => $goal->title,
            'details' => $goal->details ?? [],
            'is_active' => $goal->is_active,
            'created_at' => $goal->created_at?->toIso8601String(),
            'updated_at' => $goal->updated_at?->toIso8601String(),
        ];
    }
}