<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * ExportPortfolioReportRequest
 *
 * Validates filter and selection parameters for exporting a multi-project portfolio report.
 */
class ExportPortfolioReportRequest extends FormRequest
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
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer'],
            'status' => ['nullable', 'string', 'in:pending,ongoing,delayed,completed'],
            'scope' => ['nullable', 'string', 'in:active,archived,all'],
            'search' => ['nullable', 'string', 'max:255'],
            'disposition' => ['nullable', 'string', 'in:download,stream'],
        ];
    }
}
