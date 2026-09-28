<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGradeLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:grade_levels,name'],
            'sequence' => ['required', 'integer', 'min:1', 'max:65535', 'unique:grade_levels,sequence'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This grade level already exists.', 'sequence.unique' => 'This grade order is already used.'];
    }
}
