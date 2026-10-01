<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /**
     * إنشاء حساب جديد.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->validated('name'),
            'email'    => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $user->profile()->create();
        $user->settings()->create();

        // 📧 أرسل بريد التفعيل (بدون إفشال التسجيل إذا فشل البريد)
        try {
            $user->notify(new VerifyEmailNotification());
        } catch (\Throwable $e) {
            Log::error('فشل إرسال بريد التحقق: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);
        }

        // ⏱️ Token صالح 7 أيام
        $expiresAt = now()->addDays(7);

        $token = $user->createToken(
            'api-token',
            ['*'],
            $expiresAt
        )->plainTextToken;

        return $this->success([
            'message'           => 'تم إنشاء الحساب. تحقق من بريدك لتفعيله.',
            'user'              => $this->userResource($user),
            'token'             => $token,
            'expires_at'        => $expiresAt->toIso8601String(),
            'email_verified'    => false,
            'verification_sent' => true,
        ], 201);
    }

    /**
     * تسجيل الدخول.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $password = $request->validated('password');

        // 1. ابحث عن المستخدم
        $user = User::where('email', $email)->first();

        // 2. لو ما وُجد، افحص Soft-deleted
        if (!$user) {
            $deletedUser = User::onlyTrashed()
                ->where('email', $email)
                ->first();

            if ($deletedUser) {
                $daysLeft = 30 - now()->diffInDays($deletedUser->deleted_at);

                return response()->json([
                    'message'     => 'هذا الحساب محذوف.',
                    'reason'      => 'deleted',
                    'details'     => "تم حذف حسابك. يمكن استعادته خلال {$daysLeft} يومًا.",
                    'can_restore' => $daysLeft > 0,
                    'deleted_at'  => $deletedUser->deleted_at?->toIso8601String(),
                ], 403);
            }
        }

        // 3. ⚠️ حماية Timing Attack: دائماً استدعِ Hash::check
        $dummyHash = '$2y$12$' . str_repeat('X', 53);
        $hashToCheck = $user?->password ?? $dummyHash;

        $passwordValid = Hash::check($password, $hashToCheck);

        // 4. لو أي منهما فشل → رسالة موحّدة
        if (!$user || !$passwordValid) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
            ]);
        }

        // 5. أنشئ Token مع مدة صلاحية
        $remember = $request->boolean('remember');

        $tokenName = $remember ? 'remember-token' : 'api-token';

        // ⏱️ المدة:
        //   - بدون تذكرني: 7 أيام
        //   - مع تذكرني: 60 يومًا
        $expiresAt = $remember
            ? now()->addDays(60)
            : now()->addDays(7);

        $token = $user->createToken(
            $tokenName,
            ['*'],
            $expiresAt
        )->plainTextToken;

        return $this->success([
            'message'    => 'تم تسجيل الدخول بنجاح.',
            'user'       => $this->userResource($user),
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'remember'   => $remember,
        ]);
    }

    /**
     * تسجيل الخروج.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([
            'message' => 'تم تسجيل الخروج.',
        ]);
    }

    /**
     * بيانات المستخدم الحالي.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $this->userResource($request->user()),
        ]);
    }

    /**
     * تحديث معلومات المستخدم الحالي.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'min:2', 'max:100'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
        ], [
            'name.min'     => 'الاسم يجب أن يكون حرفين على الأقل.',
            'name.max'     => 'الاسم طويل جدًا.',
            'email.email'  => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'email.max'    => 'البريد الإلكتروني طويل جدًا.',
        ]);

        $user->update($validated);

        return $this->success([
            'message' => 'تم تحديث المعلومات.',
            'user'    => $this->userResource($user->fresh()),
        ]);
    }

    /**
     * تحويل User إلى array نظيف.
     */
    private function userResource(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}