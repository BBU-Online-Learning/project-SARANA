<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use App\Models\Role;
use App\Services\ClassAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->name === Role::SUPER_ADMIN
            && app(ClassAccessService::class)->ready($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['defaults' => ['required', 'array']];
        foreach (array_keys(AppSetting::DEFAULT_PREFERENCES) as $key) {
            $rules['defaults.'.$key] = ['required', 'boolean'];
        }
        $rules['defaults.message_tone'] = ['required', 'string', Rule::in(array_keys(AppSetting::MESSAGE_TONES))];
        $rules['defaults.call_tone'] = ['required', 'string', Rule::in(array_keys(AppSetting::CALL_TONES))];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'Choose a default for :attribute.',
            'boolean' => 'Choose a valid default for :attribute.',
            'defaults.message_tone.in' => 'Choose one of the built-in message sounds.',
            'defaults.call_tone.in' => 'Choose one of the built-in call sounds.',
        ];
    }
}
