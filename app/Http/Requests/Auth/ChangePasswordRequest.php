<?php

namespace App\Http\Requests\Auth;

class ChangePasswordRequest extends VerifyAccountRequest
{
    public function rules(): array
    {
        return parent::rules() + ['password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', 'different:current_password']];
    }
}
