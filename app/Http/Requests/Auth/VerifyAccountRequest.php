<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:1024'], 'code' => ['nullable', 'digits:6']];
    }

    public function messages(): array
    {
        return [
            'code.digits' => 'Enter a six-digit authenticator code.',
            'secret.prohibited' => 'Use the setup secret held by this session.',
            'current_password.required' => 'Confirm your current password.',
            'password.min' => 'Use a password of at least 12 characters.',
        ];
    }
}
