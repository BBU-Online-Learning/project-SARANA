<?php

namespace App\Http\Requests\Coursework;

use App\Models\CourseworkSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveCourseworkDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        abort_unless($assignment && (int) $assignment->school_class_id === (int) $this->route('schoolClass')?->id, 404);

        return Gate::allows('create', [CourseworkSubmission::class, $assignment]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.max' => 'Written work may not exceed 20,000 characters.',
            'attachments.max' => 'Attach no more than five files at once.',
            'attachments.*.mimes' => 'Use a PDF, Office document, text file, JPG, or PNG.',
            'attachments.*.max' => 'Each file must be 10 MB or smaller.',
        ];
    }
}
