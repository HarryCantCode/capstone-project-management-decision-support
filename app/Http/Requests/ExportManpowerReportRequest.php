<?php

namespace App\Http\Requests;

use App\Models\Personnel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ExportManpowerReportRequest
 *
 * Validates selected personnel, expertise filters, deployment status filters,
 * and the option to include recent projects for each personnel.
 */
class ExportManpowerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'export_mode' => ['nullable', 'string', 'in:selected,filtered'],
            'personnel_ids' => ['nullable', 'array'],
            'personnel_ids.*' => ['integer'],
            'expertise' => ['nullable', 'string', Rule::in(Personnel::EXPERTISE_OPTIONS)],
            'status' => ['nullable', 'string', 'in:all,assigned,not_assigned,active,archived'],
            'search' => ['nullable', 'string', 'max:255'],
            'include_recent_projects' => ['nullable', 'boolean'],
            'format' => ['nullable', 'string', 'in:pdf,csv'],
            'disposition' => ['nullable', 'string', 'in:download,stream'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expertise.in' => 'The selected expertise filter is invalid.',
        ];
    }
}
