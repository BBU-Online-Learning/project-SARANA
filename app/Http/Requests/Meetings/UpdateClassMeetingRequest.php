<?php

namespace App\Http\Requests\Meetings;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateClassMeetingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('meeting'));
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
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $start = CarbonImmutable::parse($this->input('starts_at'), config('app.timezone'));
            $end = CarbonImmutable::parse($this->input('ends_at'), config('app.timezone'));
            if ($start->diffInMinutes($end) > 480) {
                $validator->errors()->add('ends_at', 'A meeting can last no more than eight hours.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Enter a meeting title.',
            'starts_at.after' => 'Choose a future start time.',
            'starts_at.date_format' => 'Choose a valid start date and time.',
            'ends_at.after' => 'End time must follow start time.',
            'ends_at.date_format' => 'Choose a valid end date and time.',
        ];
    }
}
