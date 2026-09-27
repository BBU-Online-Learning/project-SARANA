<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after:starts_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct'],
            'results_release' => ['required', Rule::in(['immediate', 'after_due', 'manual'])],
            'show_correct_answers' => ['nullable', 'boolean'],
        ];
    }
}
