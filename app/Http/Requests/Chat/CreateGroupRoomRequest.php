<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGroupRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(\App\Services\Chat\ChatAccessService::class)->ready($this->user());
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['prohibited'], 'created_by' => ['prohibited'], 'role' => ['prohibited'],
            'role_id' => ['prohibited'], 'type' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],
            'members' => [
                'nullable',
                'array',
                'max:30',
            ],
            'members.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.min' => 'Group name must be at least 3 characters.',
            'name.max' => 'Group name may not be longer than 100 characters.',
            'members.array' => 'Members must be a valid list.',
            'members.max' => 'Too many members selected.',
            'members.*.integer' => 'Invalid member selection.',
            'members.*.different' => 'You cannot add yourself as a group member.',
            'members.*.exists' => 'One of the selected members does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
