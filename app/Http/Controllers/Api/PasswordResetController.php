<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class PasswordResetController extends ApiController
{
    /**
     * إرسال رابط إعادة تعيين كلمة المرور.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
        ]);

        $email = $request->input('email');

        // ⚠️ أمن: لا نكشف إن كان البريد مسجّلًا أم لا
        $user = User::where('email', $email)->first();

        // إذا وُجد المستخدم، أرسل البريد
        if ($user) {
            // احذف أي token قديم
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            // أنشئ token جديد
            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            // ابنِ رابط إعادة التعيين
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5500');
            $resetUrl = "{$frontendUrl}/reset-password.html?token={$token}&email=" . urlencode($email);

            // أرسل البريد
            try {
                $this->sendResetEmail($user, $resetUrl);
            } catch (\Throwable $e) {
                \Log::error('Password reset email failed: ' . $e->getMessage());

                return $this->error(
                    'تعذّر إرسال البريد. حاول لاحقًا.',
                    500
                );
            }
        }

        // ✅ نفس الرد دائمًا (أمان)
        return $this->success([
            'message' => 'إذا كان البريد مسجّلًا، فسيصلك رابط إعادة التعيين.',
        ]);
    }

    /**
     * إعادة تعيين كلمة المرور باستخدام token.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[0-9]/',
                'regex:/[A-Z]/',
                'regex:/[^a-zA-Z0-9]/',
            ],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'token.required' => 'الرابط غير صالح.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.regex' => 'كلمة المرور يجب أن تحتوي على: حرف كبير، رقم، ورمز خاص على الأقل.',
        ]);

        $email = $request->input('email');
        $token = $request->input('token');
        $password = $request->input('password');

        // ابحث عن token
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'token' => ['الرابط غير صالح أو منتهي الصلاحية.'],
            ]);
        }

        // تحقق من الـ token
        if (!Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['الرابط غير صالح.'],
            ]);
        }

        // تحقق من الصلاحية (60 دقيقة)
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'token' => ['الرابط منتهي الصلاحية. اطلب رابطًا جديدًا.'],
            ]);
        }

        // ابحث عن المستخدم
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['لا يوجد حساب بهذا البريد.'],
            ]);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($password),
        ]);

        // احذف الـ token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // احذف كل الـ tokens النشطة (logout من كل الأجهزة)
        $user->tokens()->delete();

        return $this->success([
            'message' => 'تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.',
        ]);
    }

    /**
     * إرسال بريد إعادة التعيين.
     */
    private function sendResetEmail(User $user, string $resetUrl): void
    {
        $subject = 'إعادة تعيين كلمة المرور — NEXT';
        $userName = $user->name;

        Mail::send([], [], function ($message) use ($user, $subject, $userName, $resetUrl) {
            $message->to($user->email)
                ->subject($subject)
                ->html($this->buildEmailHtml($userName, $resetUrl));
        });
    }

    /**
     * HTML البريد.
     */
    private function buildEmailHtml(string $userName, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
</head>
<body style="margin: 0; padding: 0; background: #F7F8FA; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; direction: rtl;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #F7F8FA; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 560px; background: #FFFFFF; border-radius: 20px; box-shadow: 0 12px 40px -12px rgba(17, 24, 39, 0.12); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 24px; text-align: center;">
                            <div style="display: inline-block; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;">
                                <span style="font-size: 20px; font-weight: 700; color: #111827; letter-spacing: 1px;">NEXT</span>
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.4;">
                                إعادة تعيين كلمة المرور
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <p style="margin: 0 0 16px; font-size: 15px; color: #4B5563; line-height: 1.8;">
                                مرحبًا {$userName}،
                            </p>
                            <p style="margin: 0 0 16px; font-size: 15px; color: #4B5563; line-height: 1.8;">
                                وصلنا طلب لإعادة تعيين كلمة المرور لحسابك في NEXT.
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #4B5563; line-height: 1.8;">
                                اضغط على الزر أدناه لإنشاء كلمة مرور جديدة:
                            </p>
                        </td>
                    </tr>

                    <!-- Button -->
                    <tr>
                        <td style="padding: 0 40px 24px; text-align: center;">
                            <a href="{$resetUrl}" style="display: inline-block; padding: 14px 32px; background: #2563EB; color: #FFFFFF; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 15px; letter-spacing: 0.2px;">
                                إعادة تعيين كلمة المرور
                            </a>
                        </td>
                    </tr>

                    <!-- Alternative Link -->
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #6B7280; line-height: 1.7;">
                                أو انسخ الرابط التالي والصقه في المتصفح:
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #2563EB; word-break: break-all; background: #EFF6FF; padding: 12px; border-radius: 8px; direction: ltr; text-align: left;">
                                {$resetUrl}
                            </p>
                        </td>
                    </tr>

                    <!-- Warning -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <div style="padding: 16px; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px;">
                                <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.7;">
                                    ⚠️ الرابط صالح لمدة <strong>60 دقيقة</strong> فقط.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background: #F9FAFB; border-top: 1px solid #E5E7EB;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #6B7280; line-height: 1.7;">
                                إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذا البريد.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">
                                © 2026 NEXT — افهم أولًا. تحرّك ثانيًا.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}