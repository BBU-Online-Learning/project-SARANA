<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('schoolClass')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'created_by' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'join_code' => ['prohibited'],
            'avatar' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Class name is required.',
            'name.max' => 'Class name may not be longer than 100 characters.',
            'description.max' => 'Description may not be longer than 1000 characters.',
            'owner_id.prohibited' => 'Use the explicit ownership transfer action.',
            'join_code.prohibited' => 'Join codes cannot be set manually.',
        ];
    }
}
