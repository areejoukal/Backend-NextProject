<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:academic,employment,career_growth,career_change,skills,other'],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'details' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'نوع الهدف مطلوب.',
            'type.in' => 'نوع الهدف غير صحيح.',
            'title.required' => 'عنوان الهدف مطلوب.',
            'title.min' => 'عنوان الهدف قصير جدًا.',
            'title.max' => 'عنوان الهدف طويل جدًا.',
        ];
    }
}