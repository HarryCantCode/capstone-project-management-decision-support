<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreProjectRequest
 *
 * Validates the data required to create a new project.
 */
class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('contract_price') && is_string($this->input('contract_price'))) {
            $this->merge([
                'contract_price' => str_replace(',', '', $this->input('contract_price')),
            ]);
        }

        if ($this->input('payment_status') === 'Paid') {
            $this->merge([
                'payment_terms' => null,
            ]);
        }

        if ($this->has('expenses') && is_array($this->input('expenses'))) {
            $cleanExpenses = [];
            foreach ($this->input('expenses') as $exp) {
                if (!is_array($exp)) {
                    continue;
                }
                $desc = trim((string) ($exp['description'] ?? ''));
                $resId = !empty($exp['resource_id']) ? $exp['resource_id'] : null;
                $rawAmt = $exp['amount'] ?? null;
                // If completely empty row, ignore it
                if ($desc === '' && empty($resId) && ($rawAmt === null || trim((string) $rawAmt) === '')) {
                    continue;
                }
                if (is_string($rawAmt)) {
                    $exp['amount'] = str_replace(',', '', $rawAmt);
                }
                $cleanExpenses[] = $exp;
            }
            $this->merge([
                'expenses' => $cleanExpenses,
            ]);
        }
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:10'],
            'project_type' => ['required', 'string', 'in:Residential,Commercial,Industrial,Infrastructure'],
            'rated_capacity' => ['required', 'string', 'max:100'],
            'lifting_speed' => ['required', 'string', 'max:100'],
            'no_of_stops' => ['required', 'integer', 'min:1', 'max:500'],
            'cage_dimension' => ['required', 'string', 'max:255'],
            'wall_and_ceiling' => ['required', 'string', 'max:255'],
            'flooring' => ['required', 'string', 'max:255'],
            'door_operation' => ['required', 'string', 'max:100'],
            'power_source' => ['required', 'string', 'max:100'],
            'operation' => ['required', 'string', 'max:100'],
            'warranty_period' => ['nullable', 'string', 'max:100'],
            'warranty_end_date' => ['nullable', 'date'],
            'payment_status' => ['required', 'string', 'in:Paid,Partial'],
            'payment_terms' => [
                'nullable',
                'string',
                'required_if:payment_status,Partial',
                'in:10%,20%,30%,40%,50%',
            ],
            'invoice_file' => [
                'nullable',
                'file',
                'mimes:pdf,docx,jpg,jpeg,png,webp',
                'max:20480',
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'target_completion_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value) {
                        return;
                    }

                    try {
                        $targetDate = \Carbon\Carbon::parse($value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    $startDateVal = $this->input('start_date');
                    $baseDate = now()->startOfDay();

                    if ($startDateVal) {
                        try {
                            $parsedStart = \Carbon\Carbon::parse($startDateVal)->startOfDay();
                            if ($parsedStart->gt($baseDate)) {
                                $baseDate = $parsedStart;
                            }
                        } catch (\Throwable) {
                            // Handled by date rule on start_date
                        }
                    }

                    $minAllowedDate = $baseDate->copy()->addMonth();

                    if ($targetDate->lt($minAllowedDate)) {
                        $fail('The target completion date must be at least 1 month from now or the project start date.');
                    }
                },
            ],
            'contract_price' => ['required', 'numeric', 'min:100000', 'max:999999999.99'],
            'personnel_ids' => ['required', 'array', 'min:1'],
            'personnel_ids.*' => ['required', 'integer', 'exists:personnel,id'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'expenses.*.description' => ['required_with:expenses.*.amount', 'nullable', 'string', 'max:255'],
            'expenses.*.cost_type' => ['required_with:expenses.*.description', 'nullable', 'string', 'in:materials,equipment,labor,subcontractor,other'],
            'expenses.*.quantity' => ['nullable', 'integer', 'min:1'],
            'expenses.*.amount' => ['required_with:expenses.*.description', 'nullable', 'numeric', 'min:0.01', 'max:999999999.99'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.file' => ['required', 'file', 'mimes:pdf,docx,doc,jpg,jpeg,png,webp', 'max:20480'],
            'attachments.*.category' => ['required', 'string', 'in:project_document,project_transaction,invoice,project_notes,project_drawing'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $hasInvoice = $this->hasFile('invoice_file');
            if (!$hasInvoice && $this->hasFile('attachments')) {
                $attachments = $this->file('attachments');
                if (is_array($attachments)) {
                    foreach ($attachments as $idx => $att) {
                        $cat = $this->input("attachments.{$idx}.category");
                        if (isset($att['file']) && $att['file'] instanceof \Illuminate\Http\UploadedFile && in_array($cat, ['invoice', 'project_invoice'])) {
                            $hasInvoice = true;
                            break;
                        }
                    }
                }
            }

            if ($this->input('payment_status') === 'Paid' && !$hasInvoice) {
                $validator->errors()->add('invoice_file', 'An invoice attachment (PDF, DOCX, or Image) is required when payment status is Paid in full.');
            }

            if ($this->has('expenses') && is_array($this->input('expenses'))) {
                foreach ($this->input('expenses') as $idx => $exp) {
                    if (!empty($exp['resource_id'])) {
                        $res = \App\Models\Resource::find($exp['resource_id']);
                        $qty = !empty($exp['quantity']) && (int) $exp['quantity'] > 0 ? (int) $exp['quantity'] : 1;
                        if ($res && $qty > $res->quantity_available) {
                            $validator->errors()->add("expenses.{$idx}.quantity", "Requested quantity ({$qty}) exceeds available stock ({$res->quantity_available}) for {$res->name}.");
                        }
                    }
                }
            }

            $name = trim((string) $this->input('name', ''));

            if ($name !== '') {
                $existing = \App\Models\Project::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                if ($existing) {
                    $validator->errors()->add('name', "A project named '{$existing->name}' already exists ({$existing->project_code}). Please choose a different project name.");
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'client_name.required' => 'Client name is required.',
            'client_name.max' => 'Client name cannot exceed 255 characters.',
            'address.required' => 'Site address is required.',
            'barangay.required' => 'Barangay is required.',
            'city.required' => 'City is required.',
            'project_type.required' => 'Project type is required.',
            'project_type.in' => 'Project type must be Residential, Commercial, Industrial, or Infrastructure.',
            'rated_capacity.required' => 'Rated capacity is required.',
            'lifting_speed.required' => 'Lifting speed is required.',
            'no_of_stops.required' => 'No. of stops is required.',
            'no_of_stops.integer' => 'No. of stops must be a valid number.',
            'cage_dimension.required' => 'Cage dimension (mW x mD x mH) is required.',
            'wall_and_ceiling.required' => 'Wall and ceiling specification is required.',
            'flooring.required' => 'Flooring specification is required.',
            'door_operation.required' => 'Door operation is required.',
            'power_source.required' => 'Power source specification is required.',
            'operation.required' => 'Elevator operation type is required.',
            'payment_status.required' => 'Payment status is required.',
            'payment_status.in' => 'Payment status must be Paid or Partial.',
            'payment_terms.required_if' => 'Payment Terms percentage is required when payment status is Partial.',
            'payment_terms.in' => 'Please select a valid Payment Terms percentage (10%, 20%, 30%, 40%, or 50%).',
            'invoice_file.file' => 'The invoice must be a valid uploaded file.',
            'invoice_file.mimes' => 'The invoice must be a file of type: PDF, DOCX, or Image (JPG, PNG, WEBP).',
            'invoice_file.max' => 'The invoice file may not be greater than 20MB.',
            'start_date.required' => 'Project start date is required.',
            'start_date.after_or_equal' => 'Project start date cannot be a past date.',
            'description.max' => 'Description cannot exceed 5,000 characters.',
            'target_completion_date.required' => 'Target completion date is required.',
            'target_completion_date.after_or_equal' => 'Target completion date must be on or after the start date.',
            'contract_price.required' => 'Contract price is required.',
            'contract_price.numeric' => 'Contract price must be a valid number.',
            'contract_price.min' => 'Contract price cannot be less than ₱100,000.',
            'personnel_ids.required' => 'At least one manpower personnel must be selected from the list.',
            'personnel_ids.min' => 'Please select at least one manpower personnel from the list to assign to this project.',
            'personnel_ids.*.exists' => 'The selected personnel record is invalid.',
            'expenses.*.description.required_with' => 'Expenditure description is required when an amount is provided.',
            'expenses.*.amount.required_with' => 'Expenditure amount is required when a description is provided.',
            'expenses.*.amount.numeric' => 'Expenditure amount must be a valid number.',
            'expenses.*.cost_type.in' => 'Cost type must be materials, equipment, labor, subcontractor, or other.',
            'attachments.*.file.required' => 'The attachment file is required.',
            'attachments.*.file.mimes' => 'Attachments must be PDF, DOCX, DOC, or Image (JPG, PNG, WEBP) files.',
            'attachments.*.file.max' => 'Attachment files may not exceed 20MB.',
            'attachments.*.category.required' => 'A file category is required for each attachment.',
            'attachments.*.category.in' => 'The selected file category is invalid.',
        ];
    }
}
