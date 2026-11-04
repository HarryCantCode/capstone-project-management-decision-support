<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a password satisfies the project's security complexity requirements:
 * - Minimum 8 characters in length
 * - Contains a mix of symbols (at least 1 special character)
 * - Contains at least 1 number (0-9)
 * - Contains at least 1 capital letter (A-Z)
 * - Cannot contain the user's name(s) or birthdate
 */
class PasswordComplexity implements ValidationRule
{
    /**
     * The user whose personal details should not appear in the password.
     */
    protected ?User $user;

    /**
     * Create a new rule instance.
     */
    public function __construct(?User $user = null)
    {
        $this->user = $user;
    }

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

        if ($this->user) {
            $this->validateNoPersonalDetails($password, $fail);
        }
    }

    /**
     * Ensure the password does not contain any of the user's names or birthdate.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    protected function validateNoPersonalDetails(string $password, Closure $fail): void
    {
        $lowerPassword = strtolower($password);

        // Check user names (first name, middle name, last name, full name, or tokens within)
        $nameCandidates = [
            $this->user->first_name,
            $this->user->middle_name,
            $this->user->last_name,
            $this->user->name,
        ];

        $nameTokens = [];
        foreach ($nameCandidates as $candidate) {
            if (empty($candidate)) {
                continue;
            }
            // Split composite names into parts (e.g., "Mary Jane" -> ["Mary", "Jane"])
            $parts = preg_split('/[\s,\.\-_]+/', (string) $candidate);
            foreach ($parts as $part) {
                $trimmed = trim($part);
                // Disallow names/tokens that are at least 3 characters long to avoid false positives with single letters
                if (mb_strlen($trimmed) >= 3) {
                    $nameTokens[] = mb_strtolower($trimmed);
                }
            }
        }

        $nameTokens = array_unique($nameTokens);

        foreach ($nameTokens as $token) {
            if (str_contains($lowerPassword, $token)) {
                $fail('Your password cannot contain your name.');

                return;
            }
        }

        // Check birthdate in various common formats (e.g., YYYY-MM-DD, MMDDYYYY, DDMMYYYY, YYYYMMDD, MMDDYY, etc.)
        if (! empty($this->user->birthdate)) {
            $birthdateCarbon = is_string($this->user->birthdate)
                ? \Carbon\Carbon::parse($this->user->birthdate)
                : $this->user->birthdate;

            $year = $birthdateCarbon->format('Y');         // 1995
            $shortYear = $birthdateCarbon->format('y');    // 95
            $month = $birthdateCarbon->format('m');        // 05
            $shortMonth = $birthdateCarbon->format('n');   // 5
            $day = $birthdateCarbon->format('d');          // 09
            $shortDay = $birthdateCarbon->format('j');     // 9

            $birthdatePatterns = [
                $year . $month . $day,         // 19950509
                $month . $day . $year,         // 05091995
                $day . $month . $year,         // 09051995
                $shortYear . $month . $day,    // 950509
                $month . $day . $shortYear,    // 050995
                $day . $month . $shortYear,    // 090595
                $shortMonth . $shortDay . $year, // e.g. 591995
                $shortDay . $shortMonth . $year, // e.g. 951995
                $birthdateCarbon->format('Y-m-d'), // 1995-05-09
                $birthdateCarbon->format('m/d/Y'), // 05/09/1995
                $birthdateCarbon->format('d/m/Y'), // 09/05/1995
                $birthdateCarbon->format('m-d-Y'), // 05-09-1995
                $birthdateCarbon->format('d-m-Y'), // 09-05-1995
            ];

            // Normalize password by removing non-alphanumeric chars to catch birthdates even with delimiters or surrounding chars
            $passwordDigitsOnly = preg_replace('/[^0-9]/', '', $password);

            $digitSequencesToCheck = [
                $year . $month . $day,
                $month . $day . $year,
                $day . $month . $year,
                $month . $day . $shortYear,
                $day . $month . $shortYear,
            ];

            foreach ($digitSequencesToCheck as $digits) {
                if (! empty($digits) && str_contains($passwordDigitsOnly, $digits)) {
                    $fail('Your password cannot contain your birthdate.');

                    return;
                }
            }

            foreach ($birthdatePatterns as $pattern) {
                if (! empty($pattern) && str_contains($lowerPassword, strtolower($pattern))) {
                    $fail('Your password cannot contain your birthdate.');

                    return;
                }
            }
        }
    }
}
