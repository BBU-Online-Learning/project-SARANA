<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveReportingPeriodRequest extends FormRequest
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
        $period = $this->route('reportingPeriod');

        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('reporting_periods', 'id')],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', Rule::unique('reporting_periods', 'code')->where('academic_year_id', $this->integer('academic_year_id'))->ignore($period?->id)],
            'sequence' => ['required', 'integer', 'min:1', 'max:65535'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This reporting period code already exists in the academic year.',
            'ends_on.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
