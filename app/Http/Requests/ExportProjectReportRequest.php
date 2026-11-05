<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * ExportProjectReportRequest
 *
 * Validates optional customization parameters when exporting a single project PDF report.
 */
class ExportProjectReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'include_overview' => ['nullable', 'boolean'],
            'include_financials' => ['nullable', 'boolean'],
            'include_specs' => ['nullable', 'boolean'],
            'include_expenditures' => ['nullable', 'boolean'],
            'include_manpower' => ['nullable', 'boolean'],
            'include_timeline' => ['nullable', 'boolean'],
            'disposition' => ['nullable', 'string', 'in:download,stream'],
        ];
    }
}
