<?php

namespace App\Http\Requests\Chat;

use App\Models\ChatRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartVoiceCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room instanceof ChatRoom
            && $room->type === 'direct'
            && $this->user()?->can('access', $room) === true;
    }

    public function rules(): array
    {
        return [
            'call_type' => ['sometimes', 'required', Rule::in(['audio', 'video'])],
            'client_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['call_type.in' => 'Choose an audio or video call.'];
    }
}
