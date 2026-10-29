<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * TransitionProjectRequest
 *
 * Validates the parameters when a project's status is being transitioned.
 */
class TransitionProjectRequest extends FormRequest
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

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,ongoing,delayed,completed'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Select the next project status.',
            'status.in' => 'Select a valid project status.',
            'notes.max' => 'Transition notes cannot exceed 500 characters.',
        ];
    }
}
