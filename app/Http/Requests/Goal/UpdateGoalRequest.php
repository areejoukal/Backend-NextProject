<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:200'],
            'details' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'عنوان الهدف قصير جدًا.',
            'title.max' => 'عنوان الهدف طويل جدًا.',
        ];
    }
}