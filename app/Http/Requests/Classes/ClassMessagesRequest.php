<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class ClassMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewChannel', [$this->route('schoolClass'), $this->route('channel')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'after_id' => ['sometimes', 'integer', 'min:0', 'prohibits:before_id'],
            'before_id' => ['sometimes', 'integer', 'min:1'],
            'sync_only' => ['sometimes', 'boolean'],
            'visible_ids' => ['sometimes', 'array', 'max:200'],
            'visible_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'after_id.integer' => 'The message cursor must be an integer.',
            'after_id.min' => 'The message cursor cannot be negative.',
            'after_id.prohibits' => 'Use only one history direction.',
            'before_id.integer' => 'The history cursor must be an integer.',
            'before_id.min' => 'The history cursor must be positive.',
            'visible_ids.max' => 'Synchronize at most 200 visible messages.',
            'visible_ids.*.integer' => 'Message identifiers must be integers.',
        ];
    }
}
