<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Same rules as store — status transitions use the separate
     * ProjectController::transition() route and its own inline validation.
     *
     * @return array<string, list<string>>
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
            'start_date' => ['required', 'date'],
            'target_completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'contract_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'update_notes' => ['required', 'string', 'max:1000'],
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
            'start_date.required' => 'Project start date is required.',
            'description.max' => 'Description cannot exceed 5,000 characters.',
            'target_completion_date.required' => 'Target completion date is required.',
            'target_completion_date.after_or_equal' => 'Target completion date must be on or after the start date.',
            'contract_price.required' => 'Contract price is required.',
            'contract_price.numeric' => 'Contract price must be a valid number.',
            'contract_price.min' => 'Contract price cannot be negative.',
            'update_notes.required' => 'Reason for update / audit note is required.',
            'update_notes.max' => 'Reason for update cannot exceed 1,000 characters.',
        ];
    }
}
