<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('schoolClass')) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:student,teacher'],
            'school_class_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Choose an account to enroll.',
            'user_id.exists' => 'Choose an existing eligible account.',
            'role.in' => 'Only student or teacher membership can be assigned here.',
            'school_class_id.prohibited' => 'The class is determined by the route.',
        ];
    }
}
