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
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'role' => ['prohibited'], 'owner_id' => ['prohibited'], 'created_by' => ['prohibited'],
            'type' => ['prohibited'], 'role_id' => ['prohibited'], 'members' => ['prohibited'],
            'is_private' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('description'))) {
            $description = trim($this->input('description'));
            $this->merge(['description' => $description === '' ? null : $description]);
        }
    }

    public function messages(): array
    {
        return ['name.required' => 'Enter a group name.', 'name.string' => 'Group name must be text.',
            'name.min' => 'Use at least 3 characters.', 'name.max' => 'Use at most 100 characters.',
            'description.string' => 'Group description must be text.', 'description.max' => 'Use at most 1000 characters for the description.',
            'avatar.image' => 'Choose a valid group image.', 'avatar.mimes' => 'Choose a JPG, PNG or WebP group image.',
            'avatar.extensions' => 'Choose a JPG, PNG or WebP group image.', 'avatar.max' => 'The group image must be no larger than 2 MB.',
            'avatar.dimensions' => 'The group image must be at most 4096 pixels wide and tall.'];
    }
}
