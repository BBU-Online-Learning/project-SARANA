<?php

namespace App\Http\Requests\Coursework;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCourseworkAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        abort_unless($assignment && (int) $assignment->school_class_id === (int) $this->route('schoolClass')?->id, 404);

        return Gate::allows('update', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'max_points' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'due_at' => ['nullable', 'date'],
            'allow_resubmissions' => ['required', 'boolean'],
            'subject_id' => ['nullable', 'integer', Rule::exists('class_subjects', 'subject_id')->where('school_class_id', $this->route('schoolClass')->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Enter an assignment title.',
            'max_points.required' => 'Set the maximum points.',
            'max_points.decimal' => 'Use no more than two decimal places.',
            'due_at.date' => 'Enter a valid due date.',
            'subject_id.exists' => 'Choose a subject assigned to this class.',
        ];
    }
}
