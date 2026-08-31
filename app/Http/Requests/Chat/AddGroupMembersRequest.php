<?php

namespace App\Http\Requests\Chat;

class AddGroupMembersRequest extends UpdateGroupRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'name' => ['prohibited'],
            'members' => ['required', 'array', 'min:1', 'max:30'],
            'members.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'members.required' => 'Select at least one member.',
            'members.array' => 'Members must be a list.', 'members.max' => 'Select at most 30 members.',
            'members.*.integer' => 'Invalid member selection.', 'members.*.distinct' => 'Select each member once.'];
    }
}
