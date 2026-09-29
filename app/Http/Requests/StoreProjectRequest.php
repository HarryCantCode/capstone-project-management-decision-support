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
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', 'in:Residential,Commercial,Industrial,Infrastructure'],
            'warranty_period' => ['nullable', 'string', 'max:100'],
            'warranty_end_date' => ['nullable', 'date'],
            'payment_status' => ['required', 'string', 'in:Paid,Partial'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'target_completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'contract_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ];
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
            'payment_status.required' => 'Payment status is required.',
            'payment_status.in' => 'Payment status must be Paid or Partial.',
            'start_date.required' => 'Project start date is required.',
            'start_date.after_or_equal' => 'Project start date cannot be a past date.',
            'description.max' => 'Description cannot exceed 5,000 characters.',
            'target_completion_date.required' => 'Target completion date is required.',
            'target_completion_date.after_or_equal' => 'Target completion date must be on or after the start date.',
            'contract_price.required' => 'Contract price is required.',
            'contract_price.numeric' => 'Contract price must be a valid number.',
            'contract_price.min' => 'Contract price cannot be negative.',
        ];
    }
}
