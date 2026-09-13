<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class MarkMessagesReadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('access', $this->route('room'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['up_to_message_id' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['up_to_message_id.required' => 'Select the last visible message.',
            'up_to_message_id.integer' => 'The message ID must be an integer.',
            'up_to_message_id.min' => 'The message ID must be positive.'];
    }
}
