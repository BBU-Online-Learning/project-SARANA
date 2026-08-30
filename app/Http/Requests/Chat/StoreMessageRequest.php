<?php
//  app\Http\Requests\Chat\StoreMessageRequest.php 

namespace App\Http\Requests\Chat;

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

            'attachments' => [
                'nullable',
                'array',
                'max:' . config('chat.max_attachments_per_message'),
            ],

            'attachments.*' => [
                'file',
                'max:' . config('chat.max_attachment_size_kb'),
                'mimes:' . implode(',', config('chat.allowed_attachment_extensions')),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE DATA
    |--------------------------------------------------------------------------
    */

    protected function prepareForValidation(): void
    {
        if ($this->body) {

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
}
