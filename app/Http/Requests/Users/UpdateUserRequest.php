<?php

namespace App\Http\Requests\Users;

use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ]);
    }
}
