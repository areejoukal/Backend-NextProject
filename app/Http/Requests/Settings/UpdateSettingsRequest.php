<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // ⬅️ يجب أن يكون true
    }

    public function rules(): array
    {
        return [
            'step_reminders' => ['sometimes', 'boolean'],
            'reassessment_reminders' => ['sometimes', 'boolean'],
            'change_summary' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'step_reminders.boolean' => 'قيمة تذكيرات الخطوات غير صحيحة.',
            'reassessment_reminders.boolean' => 'قيمة تذكير إعادة التقييم غير صحيحة.',
            'change_summary.boolean' => 'قيمة ملخص التغييرات غير صحيحة.',
        ];
    }
}