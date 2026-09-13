<?php

//  app\Http\Requests\Chat\StoreMessageRequest.php

namespace App\Http\Requests\Chat;

use App\Rules\SafeChatAttachment;
use App\Services\Chat\StickerCatalog;
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

            'sticker_id' => [
                'nullable',
                'string',
                Rule::in(StickerCatalog::ids()),
            ],

            'attachment_context' => [
                'nullable',
                Rule::in(['voice']),
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
            $hasSticker = filled($this->input('sticker_id'));

            if (! $hasText && ! $hasAttachments && ! $hasSticker) {

                $validator->errors()->add(
                    'body',
                    'A message must contain text, an attachment, or a sticker.'
                );
            }

            if ($hasSticker && ($hasText || $hasAttachments)) {
                $validator->errors()->add('sticker_id', 'A sticker must be sent as its own message.');
            }

            if ($this->input('attachment_context') === 'voice') {
                $attachments = $this->file('attachments', []);
                $voiceFile = is_array($attachments) ? ($attachments[0] ?? null) : null;
                $extension = $voiceFile instanceof \Illuminate\Http\UploadedFile
                    ? strtolower($voiceFile->getClientOriginalExtension())
                    : '';

                if (count((array) $attachments) !== 1 || ! in_array($extension, [
                    'ogg', 'oga', 'webm', 'mp3', 'wav', 'm4a', 'aac', 'mpeg', 'mpga',
                ], true)) {
                    $validator->errors()->add('attachment_context', 'A voice message must contain one supported audio recording.');
                }
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
            'attachment_context.in' => 'The attachment context is invalid.',
        ];
    }
}
