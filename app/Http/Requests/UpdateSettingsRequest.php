<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use App\Services\ClassAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && app(ClassAccessService::class)->ready($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = array_fill_keys(array_keys(AppSetting::DEFAULT_PREFERENCES), ['required', 'boolean']);
        $rules['message_tone'] = ['required', 'string', Rule::in(array_keys(AppSetting::MESSAGE_TONES))];
        $rules['call_tone'] = ['required', 'string', Rule::in(array_keys(AppSetting::CALL_TONES))];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'Choose an option for :attribute.',
            'boolean' => 'Choose a valid option for :attribute.',
            'message_tone.in' => 'Choose one of the built-in message sounds.',
            'call_tone.in' => 'Choose one of the built-in call sounds.',
        ];
    }
}
