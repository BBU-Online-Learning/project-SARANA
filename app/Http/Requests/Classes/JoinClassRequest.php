<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class JoinClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(\App\Services\ClassAccessService::class)->ready($this->user());
    }

    public function rules(): array
    {
        return [
            'role' => ['prohibited'],
            'user_id' => ['prohibited'],
            'join_code' => [
                'required',
                'string',
                'min:4',
                'max:32',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'join_code.required' => 'Join code is required.',
            'join_code.min' => 'Join code looks too short.',
            'join_code.max' => 'Join code looks too long.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('join_code'))) {
            $this->merge([
                'join_code' => strtoupper(trim($this->input('join_code'))),
            ]);
        }
    }
}
