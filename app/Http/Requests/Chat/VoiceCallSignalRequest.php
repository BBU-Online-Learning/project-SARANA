<?php

namespace App\Http\Requests\Chat;

use Illuminate\Validation\Rule;

class VoiceCallSignalRequest extends VoiceCallActionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['required', 'string', Rule::in(['offer', 'answer', 'ice', 'media', 'restart'])],
            'data' => ['present', 'array'],
            'data.description' => ['required_if:type,offer,answer', 'array'],
            'data.description.type' => ['required_if:type,offer,answer', 'string', Rule::in(['offer', 'answer'])],
            'data.description.sdp' => ['required_if:type,offer,answer', 'string', 'max:24576'],
            'data.candidate' => ['required_if:type,ice', 'array'],
            'data.candidate.candidate' => ['required_if:type,ice', 'string', 'max:8192'],
            'data.candidate.sdpMid' => ['nullable', 'string', 'max:255'],
            'data.candidate.sdpMLineIndex' => ['nullable', 'integer', 'min:0'],
            'data.candidate.usernameFragment' => ['nullable', 'string', 'max:255'],
            'data.camera' => ['required_if:type,media', 'boolean'],
            'data.microphone' => ['required_if:type,media', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The voice-call signal type is invalid.',
            'data.array' => 'The voice-call signal payload must be an object.',
            'data.description.sdp.required_if' => 'An SDP description is required for this signal.',
            'data.candidate.candidate.required_if' => 'An ICE candidate is required for this signal.',
        ];
    }
}
