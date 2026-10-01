<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'age' => ['nullable', 'integer', 'min:16', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'education' => ['nullable', 'in:high_school,diploma,bachelor,master,phd,other'],
            'field' => ['nullable', 'string', 'max:150'],
            'experience' => ['nullable', 'in:beginner,learning,some_experience,professional,experienced'],
            'background' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'age.integer' => 'العمر يجب أن يكون رقمًا.',
            'age.min' => 'العمر يجب أن يكون 16 على الأقل.',
            'age.max' => 'العمر يجب أن يكون 100 أو أقل.',
            'location.max' => 'الموقع طويل جدًا.',
            'education.in' => 'المستوى التعليمي غير صحيح.',
            'field.max' => 'المجال طويل جدًا.',
            'experience.in' => 'مستوى الخبرة غير صحيح.',
            'background.max' => 'النص طويل جدًا (الحد 500 حرف).',
        ];
    }
}