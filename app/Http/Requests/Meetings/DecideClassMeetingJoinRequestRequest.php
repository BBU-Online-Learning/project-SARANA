<?php

namespace App\Http\Requests\Meetings;

use App\Models\ClassMeetingJoinRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DecideClassMeetingJoinRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $schoolClass = $this->route('schoolClass');
        $meeting = $this->route('meeting');
        $joinRequest = $this->route('joinRequest');

        return $meeting !== null && $schoolClass !== null && $joinRequest !== null
            && (int) $meeting->school_class_id === (int) $schoolClass->id
            && (int) $joinRequest->class_meeting_id === (int) $meeting->id
            && Gate::allows('manageJoinRequests', $meeting);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([ClassMeetingJoinRequest::ADMITTED, ClassMeetingJoinRequest::DENIED])],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Choose whether to admit or deny this person.',
            'decision.in' => 'Choose a valid meeting admission decision.',
        ];
    }
}
