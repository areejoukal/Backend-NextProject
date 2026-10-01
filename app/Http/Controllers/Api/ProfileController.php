<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    /**
     * جلب الملف الشخصي.
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            $profile = $request->user()->profile()->create();
        }

        return $this->success([
            'profile' => $this->profileResource($profile),
        ]);
    }

    /**
     * تحديث الملف الشخصي.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile ?? $user->profile()->create();

        $profile->update($request->validated());

        return $this->success([
            'message' => 'تم تحديث الملف الشخصي.',
            'profile' => $this->profileResource($profile->fresh()),
        ]);
    }

    private function profileResource($profile): array
    {
        return [
            'age' => $profile->age,
            'location' => $profile->location,
            'education' => $profile->education,
            'field' => $profile->field,
            'experience' => $profile->experience,
            'background' => $profile->background,
            'is_complete' => $this->isComplete($profile),
        ];
    }

    private function isComplete($profile): bool
    {
        // الحد الأدنى: التعليم + المجال + الخبرة
        return !empty($profile->education)
            && !empty($profile->field)
            && !empty($profile->experience);
    }
}