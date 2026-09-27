<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class IndexClassRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Enter a class name or description to search.',
            'search.max' => 'Keep your search under 100 characters.',
            'status.in' => 'Choose all, active, or archived classes.',
        ];
    }
}
