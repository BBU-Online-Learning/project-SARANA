<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolClassChannelMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schoolClass = $this->route('schoolClass');
        $channel = $this->route('channel');

        if (! $schoolClass || ! $channel) {
            return false;
        }

        if ((int) $schoolClass->id !== (int) $channel->school_class_id) {
            return false;
        }

        return $this->user()?->can('sendMessage', [$schoolClass, $channel]) ?? false;
    }

    public function rules(): array
    {
        return [
            'sender_id' => ['prohibited'],
            'school_class_channel_id' => ['prohibited'],
            'school_class_id' => ['prohibited'],
            'is_edited' => ['prohibited'],
            'edited_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'body' => [
                'required',
                'string',
                'max:5000',
            ],
            'client_uuid' => [
                'nullable',
                'uuid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Message text is required.',
            'body.string' => 'Message text must be a string.',
            'body.max' => 'Message may not be longer than 5000 characters.',
            'client_uuid.uuid' => 'Invalid client identifier.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge([
                'body' => trim(strip_tags($this->input('body'))),
            ]);
        }
    }
}
