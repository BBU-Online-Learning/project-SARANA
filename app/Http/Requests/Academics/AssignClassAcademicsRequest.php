<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignClassAcademicsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'subject_ids' => ['sometimes', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ];
    }

    public function messages(): array
    {
        return ['academic_year_id.required' => 'Choose an academic year.', 'subject_ids.*.distinct' => 'Choose each subject only once.'];
    }
}
