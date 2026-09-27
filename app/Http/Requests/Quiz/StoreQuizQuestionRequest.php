<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuizQuestionRequest extends FormRequest
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
            'type' => ['required', Rule::in(['multiple_choice', 'true_false', 'multiple_answer', 'short_answer'])],
            'prompt' => ['required', 'string', 'max:10000'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'grading_mode' => ['required', Rule::in(['automatic', 'manual'])],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*.text' => ['nullable', 'string', 'max:2000'],
            'correct_options' => ['nullable', 'array'],
            'correct_options.*' => ['integer', 'min:0', 'max:19'],
            'accepted_answers' => ['nullable', 'string', 'max:5000'],
            'case_sensitive' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->string('type')->toString();
            if (in_array($type, ['multiple_choice', 'multiple_answer'], true)) {
                $options = collect($this->input('options', []))->filter(fn ($option): bool => filled($option['text'] ?? null));
                if ($options->count() < 2) {
                    $validator->errors()->add('options', 'Add at least two answer options.');
                }
                $correct = collect($this->input('correct_options', []))->filter(fn ($index): bool => $options->has((int) $index));
                $required = $type === 'multiple_choice' ? 1 : 1;
                if ($correct->count() < $required || ($type === 'multiple_choice' && $correct->count() !== 1)) {
                    $validator->errors()->add('correct_options', $type === 'multiple_choice' ? 'Select exactly one correct option.' : 'Select at least one correct option.');
                }
            }
            if ($type === 'short_answer' && $this->string('grading_mode')->toString() === 'automatic' && blank($this->input('accepted_answers'))) {
                $validator->errors()->add('accepted_answers', 'Add at least one accepted answer for automatic grading.');
            }
        }];
    }
}
