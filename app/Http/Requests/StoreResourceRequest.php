<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\Resource::class);
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:material,tool,equipment'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity_available' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'demand_status' => ['required', 'string', 'in:fast-moving,slow-moving'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Item name is required.',
            'resource_code.regex' => 'Item code must follow the format DEX-MAT000 (e.g. DEX-MAT001).',
            'resource_code.unique' => 'This item code is already in use.',
            'quantity_available.required' => 'Quantity is required.',
            'quantity_available.integer' => 'Quantity must be a valid integer.',
            'quantity_available.min' => 'Quantity cannot be negative.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid amount.',
            'unit_cost.required' => 'Unit cost is required.',
            'unit_cost.numeric' => 'Unit cost must be a valid amount.',
            'demand_status.required' => 'Demand status is required.',
            'demand_status.in' => 'Demand status must be either fast-moving or slow-moving.',
        ];
    }
}
