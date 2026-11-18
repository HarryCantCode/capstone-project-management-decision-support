<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportRevenueReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Manager', 'Staff']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comparison_mode' => ['nullable', 'string', 'in:monthly,yearly'],
            'year' => ['nullable', 'string', 'max:10'],
            'start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['nullable', 'string', 'max:30'],
            'end_date' => ['nullable', 'string', 'max:30'],
            'month_range_label' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:ongoing,pending,delayed,completed'],
            'scope' => ['nullable', 'string', 'in:active,archived,all'],
            'format' => ['nullable', 'string', 'in:pdf,csv'],
            'disposition' => ['nullable', 'string', 'in:download,stream'],
        ];
    }
}
