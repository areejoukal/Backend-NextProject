<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends ApiController
{
    /**
     * جلب الإعدادات.
     */
    public function show(Request $request): JsonResponse
    {
        $settings = $this->getOrCreateSettings($request);

        return $this->success([
            'settings' => $this->settingsResource($settings),
        ]);
    }

    /**
     * تحديث الإعدادات.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->getOrCreateSettings($request);

        $validated = $request->validated();

        if (!empty($validated)) {
            $settings->update($validated);
        }

        return $this->success([
            'message' => 'تم تحديث الإعدادات.',
            'settings' => $this->settingsResource($settings->fresh()),
        ]);
    }

    /**
     * حذف الحساب.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return $this->success([
            'message' => 'تم حذف الحساب. يمكنك التواصل معنا لاستعادته خلال 30 يومًا.',
        ]);
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    /**
     * جلب أو إنشاء إعدادات المستخدم مع defaults آمنة.
     */
    private function getOrCreateSettings(Request $request): UserSetting
    {
        $user = $request->user();
        $settings = $user->settings;

        if (!$settings) {
            $settings = $user->settings()->create([
                'step_reminders'         => true,
                'reassessment_reminders' => true,
                'change_summary'         => true,
            ]);
        }

        return $settings;
    }

    /**
     * تحويل الإعدادات لـ array مع ضمان boolean صريح.
     */
    private function settingsResource(UserSetting $settings): array
    {
        return [
            'step_reminders'         => (bool) ($settings->step_reminders ?? true),
            'reassessment_reminders' => (bool) ($settings->reassessment_reminders ?? true),
            'change_summary'         => (bool) ($settings->change_summary ?? true),
        ];
    }
}