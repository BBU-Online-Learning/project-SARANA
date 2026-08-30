<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Services\AccountManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['required', 'integer', Rule::in(app(AccountManagementService::class)->assignableRoles($this->user())->modelKeys())],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'google2fa_secret' => ['prohibited'],
            'google2fa_enabled' => ['prohibited'],
            'two_factor_secret_encrypted' => ['prohibited'],
            'two_factor_last_used_step' => ['prohibited'],
            'two_factor_recovery_token_hash' => ['prohibited'],
            'recovery_requested_by' => ['prohibited'],
            'auth_version' => ['prohibited'],
            'must_change_password' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.in' => 'You cannot assign this role.',
            'status.in' => 'Choose active, inactive or suspended.',
            'password.min' => 'Use a password of at least 8 characters.',
            '*.prohibited' => 'This field cannot be changed through account management.',
        ];
    }
}
