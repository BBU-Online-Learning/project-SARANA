<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReadRoomNotificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'room_id' => ['required', 'integer', 'exists:chat_rooms,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => 'Choose a conversation.',
            'room_id.integer' => 'Choose a valid conversation.',
            'room_id.exists' => 'This conversation is unavailable.',
        ];
    }
}
