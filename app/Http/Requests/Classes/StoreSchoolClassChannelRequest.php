<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSchoolClassChannelRequest extends FormRequest
{
    // This prevents a teacher from creating a channel in a class they do not manage.
    public function authorize(): bool
    {
        $schoolClass = $this->route('schoolClass');
        if (! $schoolClass || ! Auth::user()) {
            return false;
        }

        return Auth::user()->can('manageChannels', $schoolClass);
    }

    public function rules(): array
    {
        return [
            'school_class_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'is_default' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Channel name is required.',
            'name.max' => 'Channel name may not be longer than 100 characters.',
            'description.max' => 'Description may not be longer than 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($description) ? trim($description) : $description,
        ]);
    }
}
