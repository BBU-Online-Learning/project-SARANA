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
        return ['after_id' => ['sometimes', 'integer', 'min:0']];
    }

    public function messages(): array
    {
        return ['after_id.integer' => 'The message cursor must be an integer.', 'after_id.min' => 'The message cursor cannot be negative.'];
    }
}
