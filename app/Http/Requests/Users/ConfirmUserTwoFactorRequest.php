<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmUserTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'secret' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter the authenticator code.',
            'code.digits' => 'Enter a six-digit authenticator code.',
            'secret.prohibited' => 'The setup secret must come from the current session.',
        ];
    }
}
