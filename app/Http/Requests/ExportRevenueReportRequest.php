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
            'status' => ['nullable', 'string', 'in:ongoing,pending,delayed,completed'],
            'scope' => ['nullable', 'string', 'in:active,archived,all'],
            'format' => ['nullable', 'string', 'in:pdf,csv'],
            'disposition' => ['nullable', 'string', 'in:download,stream'],
        ];
    }
}
