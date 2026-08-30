<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'digits:6'], 'secret' => ['prohibited']];
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
