<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-classes');
    }

    public function rules(): array
    {
        return [
            'owner_id' => [
                app(\App\Services\ClassAccessService::class)->administrator($this->user()) ? 'required' : 'prohibited',
                'integer',
                'exists:users,id',
            ],
            'created_by' => ['prohibited'],
            'join_code' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.required' => 'Select an eligible application Teacher as the class owner.',
            'owner_id.prohibited' => 'Teachers create classes under their own ownership.',
            'name.required' => 'Class name is required.',
            'name.min' => 'Class name must be at least 3 characters.',
            'name.max' => 'Class name may not be longer than 100 characters.',
            'description.max' => 'Description may not be longer than 1000 characters.',
            'avatar.image' => 'Class image must be a valid image file.',
            'avatar.mimes' => 'Class image must be jpg, jpeg, png, or webp.',
            'avatar.max' => 'Class image may not be larger than 2 MB.',
        ];
    }
}
