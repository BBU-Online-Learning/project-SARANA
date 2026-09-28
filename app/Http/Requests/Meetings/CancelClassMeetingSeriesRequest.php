<?php

namespace App\Http\Requests\Meetings;

use App\Models\ClassMeeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelClassMeetingSeriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $series = $this->route('meetingSeries');

        return $series !== null && (int) $series->school_class_id === (int) $this->route('schoolClass')->id
            && Gate::allows('create', [ClassMeeting::class, $this->route('schoolClass')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
