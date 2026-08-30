<?php

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class TransferClassOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transferOwnership', $this->route('schoolClass')) ?? false;
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'confirm_transfer' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.required' => 'Select the new application Teacher owner.',
            'owner_id.exists' => 'Select an existing eligible Teacher.',
            'confirm_transfer.accepted' => 'Confirm that the previous owner will become a student member.',
        ];
    }
}
