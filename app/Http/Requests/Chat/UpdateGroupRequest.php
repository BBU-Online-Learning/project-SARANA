<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('room')?->type === 'group', 404);

        return $this->user()?->can('manageGroup', $this->route('room')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'role' => ['prohibited'], 'owner_id' => ['prohibited'], 'created_by' => ['prohibited'],
            'type' => ['prohibited'], 'role_id' => ['prohibited'], 'members' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function messages(): array
    {
        return ['name.required' => 'Enter a group name.', 'name.string' => 'Group name must be text.',
            'name.min' => 'Use at least 3 characters.', 'name.max' => 'Use at most 100 characters.'];
    }
}
