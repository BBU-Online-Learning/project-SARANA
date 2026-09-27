<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ForwardMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:20'],
            'message_ids.*' => ['required', 'integer', 'distinct', 'exists:messages,id'],
            'room_ids' => ['required', 'array', 'min:1', 'max:10'],
            'room_ids.*' => ['required', 'integer', 'distinct', 'exists:chat_rooms,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('note')) {
            $this->merge([
                'note' => trim(strip_tags((string) $this->input('note'))),
            ]);
        }
    }
}
