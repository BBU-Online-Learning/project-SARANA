<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\Auth\VerifyAccountRequest;

class RecoverUserRequest extends VerifyAccountRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return parent::rules() + [
            'identity_confirmed' => ['accepted'],
            'email' => ['prohibited'],
            'secret' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['identity_confirmed.accepted' => 'Confirm that you verified the account owner through your institution.'];
    }
}
