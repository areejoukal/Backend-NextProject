<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[0-9]/',
                'regex:/[A-Z]/',
                'regex:/[^a-zA-Z0-9]/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Name
            'name.required' => 'الاسم مطلوب.',
            'name.min' => 'الاسم يجب أن يكون حرفين على الأقل.',
            'name.max' => 'الاسم طويل جدًا.',

            // Email
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل بالفعل.',
            'email.max' => 'البريد الإلكتروني طويل جدًا.',

            // Password
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.regex' => 'كلمة المرور يجب أن تحتوي على: حرف كبير، رقم، ورمز خاص على الأقل.',

            // Confirmation
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب.',
        ];
    }
}