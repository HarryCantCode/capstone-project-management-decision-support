<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * ExportMaterialsReportRequest
 *
 * Validates selected materials and demand movement filter criteria
 * (fast-moving / slow-moving) for exporting the Materials Catalog report.
 */
class ExportMaterialsReportRequest extends FormRequest
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
            'export_mode' => ['nullable', 'string', 'in:selected,filtered'],
            'material_ids' => ['nullable', 'array'],
            'material_ids.*' => ['integer', 'exists:resources,id'],
            'demand_status' => ['nullable', 'string', 'in:fast-moving,slow-moving'],
            'stock_filter' => ['nullable', 'string', 'in:all,in_stock,low_stock,out_of_stock'],
            'search' => ['nullable', 'string', 'max:255'],
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
            'demand_status.in' => 'Demand status filter must be either fast-moving or slow-moving.',
            'material_ids.*.exists' => 'One or more selected materials could not be found in the catalog.',
        ];
    }
}
