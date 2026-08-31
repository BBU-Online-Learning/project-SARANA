<?php

//  app\Http\Requests\Chat\StoreMessageRequest.php

namespace App\Http\Requests\Chat;

use App\Rules\SafeChatAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

    public function authorize(): bool
    {
        $room = $this->route('room');

        if (! $room) {
            return false;
        }

        return $room->members()
            ->where('user_id', Auth::id())
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | RULES
    |--------------------------------------------------------------------------
    */

    public function rules(): array
    {
        return [
            'sender_id' => ['prohibited'],
            'room_id' => ['prohibited'],

            'body' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'reply_to_message_id' => [
                'nullable',
                Rule::exists('messages', 'id')
                    ->where(function ($query) {
                        $query->where(
                            'room_id',
                            $this->route('room')->id
                        );
                    }),
            ],

            'client_uuid' => [
                'nullable',
                'uuid',
            ],

            ...SafeChatAttachment::rules(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE DATA
    |--------------------------------------------------------------------------
    */

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {

            $body = trim(strip_tags($this->input('body')));

            $this->merge([
                'body' => $body === '' ? null : $body,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $hasText = filled($this->input('body'));

            $hasAttachments = $this->hasFile('attachments');

            if (! $hasText && ! $hasAttachments) {

                $validator->errors()->add(
                    'body',
                    'A message must contain text or at least one attachment.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'attachments.array' => 'Attachments must be a list of files.',
            'attachments.max' => 'You may attach at most :max files to a message.',
            'attachments.*.file' => 'Each attachment must be a valid uploaded file.',
            'attachments.*.max' => 'Each attachment must not exceed :max KB.',
        ];
    }
}
