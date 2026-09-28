<?php

namespace App\Http\Requests\Announcements;

use App\Models\ClassAnnouncement;
use Illuminate\Foundation\Http\FormRequest;

class SaveClassAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $notice = $this->route('notice');

        return $notice instanceof ClassAnnouncement
            ? $this->user()?->can('update', $notice) ?? false
            : $this->user()?->can('create', [ClassAnnouncement::class, $this->route('schoolClass'), $this->route('channel')]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give the notice a title.',
            'body.required' => 'Write the notice before saving it.',
            'expires_at.after' => 'Expiry must be in the future.',
        ];
    }
}
