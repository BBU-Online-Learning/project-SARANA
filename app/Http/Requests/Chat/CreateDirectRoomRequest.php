<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateDirectRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'different:' . Auth::id(),
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user.',
            'user_id.integer' => 'Invalid user selection.',
            'user_id.different' => 'You cannot create a direct chat with yourself.',
            'user_id.exists' => 'The selected user does not exist.',
        ];
    }
}