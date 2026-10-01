<?php

namespace App\Http\Controllers\Api;

use App\Models\Analysis;
use App\Models\NextStep;
use App\Services\Assessment\NextStepService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NextStepController extends ApiController
{
    public function __construct(
        private readonly NextStepService $service,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * توليد الخطوات من تحليل.
     */
    public function generate(Request $request, Analysis $analysis): JsonResponse
    {
        $this->authorizeAnalysis($request, $analysis);

        try {
            $steps = $this->service->generate($analysis);
        } catch (\Throwable $e) {
            return $this->error('فشل توليد الخطوات: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'message' => 'تم توليد الخطوات.',
            'steps' => array_map(fn ($s) => $this->nextStepResource($s, withDetails: true), $steps),
        ], 201);
    }

    /**
     * الخطوة الحالية: primary + secondary.
     *
     * - لو لا توجد primary نشطة، نرقّي أول secondary.
     * - نُرجع primary كاملة + قائمة secondary النشطة.
     */
    public function current(Request $request): JsonResponse
{
    $user = $request->user();

    // 1. جلب primary النشطة
    $primary = $user->nextSteps()
        ->where('role', 'primary')
        ->whereIn('status', ['pending', 'in_progress'])
        ->latest()
        ->first();

    // 2. لو لا توجد primary → رقّي أول secondary
    $promoted = false;
    if (!$primary) {
        $promotedStep = $this->promoteNextSecondary($user);
        $promoted = $promotedStep !== null;

        if ($promoted) {
            $primary = $promotedStep->fresh();
        }
    }

    // 3. لا توجد خطوات نشطة — لكن هل كانت هناك خطوات أصلاً؟
    if (!$primary) {
        // ✅ NEW: نتحقق إذا كان المستخدم عنده أي خطوات (حتى منجزة)
        $hasAnySteps = $user->nextSteps()->exists();

        // ✅ NEW: إذا عنده خطوات بس كلها منجزة → all_completed: true
        //         إذا ما عنده ولا خطوة → all_completed: false (يحتاج توليد)
        $allCompleted = $hasAnySteps;

        return $this->success([
            'step' => null,
            'secondary' => [],
            'has_step' => false,
            'all_completed' => $allCompleted,
            'needs_generation' => !$hasAnySteps,  // ✅ NEW
        ]);
    }

 // 4. جلب secondary — نرجّع 2 بس (الـ frontend يعرضهم)
$secondary = $user->nextSteps()
    ->where('role', 'secondary')
    ->whereIn('status', ['pending', 'in_progress'])
    ->where('analysis_id', $primary->analysis_id)
    ->orderBy('order')
    ->limit(2)   // ← نعرض 2 بس
    ->get();

// 5. ✅ NEW: نتحقق إذا في خطوات إضافية مخزّنة
$hasMoreSteps = $user->nextSteps()
    ->whereIn('status', ['pending', 'in_progress'])
    ->where('analysis_id', $primary->analysis_id)
    ->count() > 3;   // primary + 2 secondary + المزيد

return $this->success([
    'step' => $this->nextStepResource($primary, withDetails: true),
    'secondary' => $secondary
        ->map(fn ($s) => $this->nextStepResource($s, withDetails: true))
        ->values()
        ->toArray(),
    'has_step' => true,
    'has_more' => $hasMoreSteps,   // ✅ NEW
    'promoted' => $promoted,
    'all_completed' => false,
]);
}

    /**
     * كل الخطوات (للـ debug أو عرض السجل).
     */
    public function index(Request $request): JsonResponse
    {
        $steps = $request->user()
            ->nextSteps()
            // primary أولًا، ثم secondary، كل واحد حسب order
            ->orderByRaw("CASE WHEN role = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('order')
            ->get();

        return $this->success([
            'steps' => $steps
                ->map(fn ($s) => $this->nextStepResource($s, withDetails: true))
                ->toArray(),
        ]);
    }

    /**
     * تفاصيل خطوة واحدة.
     */
    public function show(Request $request, NextStep $nextStep): JsonResponse
    {
        $this->authorizeStep($request, $nextStep);

        return $this->success([
            'step' => $this->nextStepResource($nextStep, withDetails: true),
        ]);
    }

    /**
     * تعليم كمنجزة + ترقية الخطوة التالية.
     */
    public function complete(Request $request, NextStep $nextStep): JsonResponse
    {
        $this->authorizeStep($request, $nextStep);

        if ($nextStep->status === 'completed') {
            return $this->error('الخطوة منجزة بالفعل.', 422);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // 1. تعليم الخطوة كمكتملة
        $nextStep->update([
            'status' => 'completed',
            'note' => $validated['note'] ?? $nextStep->note,
            'completed_at' => now(),
        ]);

        // 2. تسجيل الحدث
        $request->user()->progressEvents()->create([
            'event' => 'step_completed',
            'metadata' => [
                'step_id' => $nextStep->id,
                'step_title' => $nextStep->title,
            ],
        ]);

        // 3. إشعار: أنجزت خطوة
        $this->notifications->create(
            $request->user(),
            'step_completed',
            'أحسنت! أنجزت خطوة ✓',
            $nextStep->title,
            'progress.html'
        );

        // 4. ⬇️ ترقية secondary التالية (لو هذه كانت primary)
        $nextPrimary = null;
        $nextSecondary = [];
        $promoted = false;

        if ($nextStep->role === 'primary') {
            $promotedStep = $this->promoteNextSecondary($request->user());
            $promoted = $promotedStep !== null;

            if ($promoted) {
                $nextPrimary = $promotedStep->fresh();
            }
        } else {
            // لو كانت secondary (نادرًا)، فقط نتحقق من وجود primary
            $nextPrimary = $request->user()
                ->nextSteps()
                ->where('role', 'primary')
                ->whereIn('status', ['pending', 'in_progress'])
                ->latest()
                ->first();
        }

        // 5. جلب secondary المتبقية (2 بس للعرض)
if ($nextPrimary) {
    $nextSecondary = $request->user()
        ->nextSteps()
        ->where('role', 'secondary')
        ->whereIn('status', ['pending', 'in_progress'])
        ->where('analysis_id', $nextPrimary->analysis_id)
        ->orderBy('order')
        ->limit(2)   // ✅ NEW
        ->get()
        ->map(fn ($s) => $this->nextStepResource($s, withDetails: true))
        ->values()
        ->toArray();
}

        // 6. هل اكتملت كل الخطوات؟
        $remainingSteps = $request->user()
            ->nextSteps()
            ->where('status', '!=', 'completed')
            ->count();

        $allCompleted = $remainingSteps === 0;

        // 7. إشعار: أنجز كل الخطوات
        if ($allCompleted) {
            $this->notifications->create(
                $request->user(),
                'all_steps_completed',
                'أنجزت كل خطواتك! 🎉',
                'حان وقت إعادة التقييم لمعرفة خطوتك التالية.',
                'reassessment.html'
            );
        }

        return $this->success([
            'message' => 'تم إنجاز الخطوة.',
            'step' => $this->nextStepResource($nextStep->fresh(), withDetails: true),
            'next_step' => $nextPrimary
                ? $this->nextStepResource($nextPrimary, withDetails: true)
                : null,
            'next_secondary' => $nextSecondary,
            'promoted' => $promoted,
            'all_completed' => $allCompleted,
        ]);
    }

    /**
     * حفظ ملاحظة.
     */
    public function note(Request $request, NextStep $nextStep): JsonResponse
    {
        $this->authorizeStep($request, $nextStep);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ]);

        $nextStep->update(['note' => $validated['note']]);

        return $this->success([
            'message' => 'تم حفظ الملاحظة.',
        ]);
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    /**
     * ترقية أول secondary إلى primary.
     * تُرجع الخطوة المُرقّاة أو null لو لا توجد.
     */
    private function promoteNextSecondary($user): ?NextStep
    {
        // ابحث عن أول secondary pending/in_progress
        $nextSecondary = $user
            ->nextSteps()
            ->where('role', 'secondary')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('order')
            ->first();

        if (!$nextSecondary) {
            return null;
        }

        // حوّلها إلى primary كاملة
        $nextSecondary->update([
            'role' => 'primary',
            'why' => $nextSecondary->why
                ?: 'هذه الخطوة تأتي بناءً على تقدمك. لقد أنجزت الخطوة السابقة، وهذا هو الوقت المناسب للمتابعة.',
            'outcome' => $nextSecondary->outcome
                ?: 'ستحرز تقدمًا واضحًا في الاتجاه الصحيح، وستشعر أنك تتحرك فعلًا.',
            'steps' => $nextSecondary->steps ?: [],
            'completion_criteria' => $nextSecondary->completion_criteria
                ?: 'عندما تنتهي، ستشعر أنك اقتربت خطوة حقيقية من هدفك.',
            'effort' => $nextSecondary->effort ?: 'medium',
            'time_estimate' => $nextSecondary->time_estimate ?: '30-60 دقيقة',
        ]);

        return $nextSecondary;
    }

    private function authorizeAnalysis(Request $request, Analysis $analysis): void
    {
        if ($analysis->assessment->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح.');
        }
    }

    private function authorizeStep(Request $request, NextStep $step): void
    {
        if ($step->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح.');
        }
    }

    private function nextStepResource(NextStep $step, bool $withDetails = false): array
    {
        $data = [
            'id' => $step->id,
            'order' => $step->order,
            'role' => $step->role,
            'title' => $step->title,
            'reason' => $step->reason,
            'status' => $step->status,
            'completed_at' => $step->completed_at?->toIso8601String(),
            'analysis_id' => $step->analysis_id,
        ];

        if ($withDetails) {
            $data['why'] = $step->why;
            $data['outcome'] = $step->outcome;
            $data['steps'] = $step->steps ?? [];
            $data['completion_criteria'] = $step->completion_criteria;
            $data['effort'] = $step->effort;
            $data['time_estimate'] = $step->time_estimate;
            $data['note'] = $step->note;
        }

        return $data;
    }
}