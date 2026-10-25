<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a password satisfies the project's security complexity requirements:
 * - Minimum 8 characters in length
 * - Contains a mix of symbols (at least 1 special character)
 * - Contains at least 1 number (0-9)
 * - Contains at least 1 capital letter (A-Z)
 */
class PasswordComplexity implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (strlen($password) < 8) {
            $fail('The :attribute must be at least 8 characters long.');

            return;
        }

        if (! preg_match('/[A-Z]/', $password)) {
            $fail('The :attribute must contain at least 1 capital letter.');

            return;
        }

        if (! preg_match('/[0-9]/', $password)) {
            $fail('The :attribute must contain at least 1 number.');

            return;
        }

        if (! preg_match('/[\W_]/', $password)) {
            $fail('The :attribute must contain at least 1 symbol.');

            return;
        }
    }
}
