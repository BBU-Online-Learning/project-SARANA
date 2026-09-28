<?php

namespace App\Http\Requests\Coursework;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GradeCourseworkSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        $submission = $this->route('submission');
        abort_unless($assignment && $submission
            && (int) $assignment->school_class_id === (int) $this->route('schoolClass')?->id
            && (int) $submission->coursework_assignment_id === (int) $assignment->id, 404);

        return Gate::allows('grade', $submission);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'points_awarded' => ['required', 'numeric', 'min:0', 'max:'.$this->route('assignment')->max_points, 'decimal:0,2'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'change_reason' => [Rule::requiredIf(fn (): bool => $this->route('submission')->grades()->exists()), 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'points_awarded.required' => 'Enter the points awarded.',
            'points_awarded.max' => 'Points cannot exceed the assignment maximum.',
            'change_reason.required' => 'Explain why this grade changed.',
        ];
    }
}
