<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * UpdatePersonnelRequest
 *
 * Validates updates to field personnel profile information.
 */
class UpdatePersonnelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'middle_name'     => ['nullable', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'date_of_birth'   => ['required', 'date', 'before:today'],
            'address'         => ['required', 'string', 'max:1000'],
            'expertise'       => ['required', 'string', 'in:Site Engineer,Foreman,Safety Officer,Worker'],
            'project_id'      => ['nullable', 'exists:projects,id'],
            'archive_profile' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required'    => 'First name is required.',
            'last_name.required'     => 'Last name is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before'   => 'Date of birth must be a past date.',
            'address.required'       => 'Address is required.',
            'expertise.required'     => 'Expertise / role is required.',
            'expertise.in'           => 'Expertise must be Site Engineer, Foreman, Safety Officer, or Worker.',
            'project_id.exists'      => 'The selected project does not exist.',
        ];
    }
}
