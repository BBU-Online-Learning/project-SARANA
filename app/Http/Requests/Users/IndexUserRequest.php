<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in($this->user() ? Role::manageableNames($this->user()) : [])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Enter a name or email address to search.',
            'search.max' => 'Keep the search under 100 characters.',
            'role.in' => 'Choose a role you can manage.',
            'status.in' => 'Choose active, inactive, or suspended accounts.',
            'page.integer' => 'Choose a valid results page.',
        ];
    }
}
