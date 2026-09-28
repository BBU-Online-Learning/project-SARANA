<?php

namespace App\Http\Requests;

use App\Services\GlobalSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GlobalSearchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'category' => ['sometimes', 'required', Rule::in(GlobalSearchService::CATEGORIES)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.min' => 'Enter at least two characters to search.',
            'q.max' => 'Search terms must be 100 characters or fewer.',
            'category.in' => 'Choose a valid search category.',
            'page.integer' => 'Choose a valid results page.',
        ];
    }
}
