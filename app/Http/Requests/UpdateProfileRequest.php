<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && app(\App\Services\ClassAccessService::class)->ready($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]+$/'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['name', 'phone', 'bio', 'photo', '_token', '_method']) as $field) {
                $validator->errors()->add($field, 'This field cannot be changed through your profile.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter your name.',
            'name.string' => 'Enter a valid name.',
            'name.max' => 'Your name may not exceed 255 characters.',
            'phone.string' => 'Enter a valid phone number.',
            'phone.regex' => 'Use numbers and phone punctuation only.',
            'phone.max' => 'Your phone number may not exceed 30 characters.',
            'bio.string' => 'Enter a valid bio.',
            'bio.max' => 'Your bio may not exceed 1,000 characters.',
            'photo.image' => 'Choose a valid image.',
            'photo.mimes' => 'Choose a JPG, PNG or WebP photo.',
            'photo.extensions' => 'Choose a JPG, PNG or WebP photo.',
            'photo.max' => 'Your photo must be no larger than 2 MB.',
            'photo.dimensions' => 'Your photo must be at most 4096 pixels wide and tall.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'bio'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(redirect()->route('profile.edit')->withErrors($validator)
            ->withInput($this->only(['name', 'phone', 'bio'])));
    }
}
