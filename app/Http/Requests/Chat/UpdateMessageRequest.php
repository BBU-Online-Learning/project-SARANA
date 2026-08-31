<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('message')) ?? false;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000'],
            'sender_id' => ['prohibited'], 'room_id' => ['prohibited'], 'deleted_for_everyone_at' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['body.required' => 'Message text is required.', 'body.string' => 'Message must be text.', 'body.max' => 'Use at most 5000 characters.'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }
}
