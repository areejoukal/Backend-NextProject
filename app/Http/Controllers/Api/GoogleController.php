<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends ApiController
{
    /**
     * إعادة التوجيه إلى Google.
     */
    public function redirect(Request $request)
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Callback من Google.
     */
    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            return $this->redirectWithError(
                'فشل التحقق من Google. حاول مرة أخرى.'
            );
        }

        // 1. ابحث عن المستخدم بـ google_id
        $user = User::where('google_id', $googleUser->getId())->first();

        // 2. لو ما وُجد، ابحث بالبريد
        if (!$user) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        // 3. لو ما وُجد، افحص soft-deleted
        if (!$user) {
            $deletedUser = User::onlyTrashed()
                ->where('email', $googleUser->getEmail())
                ->first();

            if ($deletedUser) {
                return $this->redirectWithError(
                    'هذا الحساب محذوف. للاستعادة، تواصل معنا.',
                    'deleted'
                );
            }
        }

        // 4. أنشئ مستخدمًا جديدًا
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName() ?? 'مستخدم NEXT',
                'email' => $googleUser->getEmail(),
                'password' => null,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            $user->profile()->create();
            $user->settings()->create();
        } else {
            // 5. تحديث بيانات المستخدم الموجود
            $updates = [];
            if (!$user->google_id) {
                $updates['google_id'] = $googleUser->getId();
            }
            if (!$user->avatar && $googleUser->getAvatar()) {
                $updates['avatar'] = $googleUser->getAvatar();
            }
            if (!$user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }
            if (!empty($updates)) {
                $user->update($updates);
            }
        }

        // 6. أنشئ Token
        // 6. أنشئ Token (60 يومًا لحساب Google)
$expiresAt = now()->addDays(60);

$token = $user->createToken(
    'google-token',
    ['*'],
    $expiresAt
)->plainTextToken;

        // 7. Redirect إلى Frontend
        return $this->redirectToFrontend($user, $token);
    }

    /**
     * Redirect إلى Frontend مع النجاح.
     */
    private function redirectToFrontend(User $user, string $token)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5500');

        $params = http_build_query([
            'token' => $token,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
        ]);

        return redirect("{$frontendUrl}/auth-callback.html?{$params}");
    }

    /**
     * Redirect إلى Frontend مع خطأ.
     */
    private function redirectWithError(string $message, string $reason = 'error')
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5500');

        $params = http_build_query([
            'error' => $message,
            'reason' => $reason,
        ]);

        return redirect("{$frontendUrl}/auth-callback.html?{$params}");
    }
}