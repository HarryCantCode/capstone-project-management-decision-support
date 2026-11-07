<?php

namespace App\Http\Requests;

use App\Models\Personnel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        // When archiving the profile, do not block the request with strict field requirements
        if ($this->boolean('archive_profile')) {
            return [
                'archive_profile' => ['required', 'boolean'],
                'first_name'      => ['nullable', 'string', 'max:255'],
                'middle_name'     => ['nullable', 'string', 'max:255'],
                'last_name'       => ['nullable', 'string', 'max:255'],
                'date_of_birth'   => ['nullable', 'date'],
                'address'         => ['nullable', 'string', 'max:1000'],
                'region'          => ['nullable', 'string', 'max:255'],
                'province'        => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],
                'barangay'        => ['nullable', 'string', 'max:255'],
                'postal_code'     => ['nullable', 'string', 'max:10'],
                'expertise'       => ['nullable', 'string'],
                'project_id'      => ['nullable', 'exists:projects,id'],
            ];
        }

        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'middle_name'     => ['nullable', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'date_of_birth'   => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'address'         => ['required', 'string', 'max:1000'],
            'region'          => ['nullable', 'string', 'max:255'],
            'province'        => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:255'],
            'barangay'        => ['nullable', 'string', 'max:255'],
            'postal_code'     => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:10'],
            'expertise'       => ['required', 'string', Rule::in(Personnel::EXPERTISE_OPTIONS)],
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
            'date_of_birth.required'        => 'Date of birth is required.',
            'date_of_birth.before_or_equal' => 'Personnel must be at least 18 years of age.',
            'date_of_birth.before'          => 'Date of birth must be a past date.',
            'address.required'       => 'Address is required.',
            'expertise.required'     => 'Expertise / role is required.',
            'expertise.in'           => 'Expertise must be one of: ' . implode(', ', Personnel::EXPERTISE_OPTIONS) . '.',
            'project_id.exists'      => 'The selected project does not exist.',
        ];
    }
}
