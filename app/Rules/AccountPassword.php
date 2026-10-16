<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The password rules for NetPlay accounts: at least 8 characters, letters and
 * digits only (no spaces or special characters), with at least one uppercase
 * letter, one lowercase letter and one digit.
 */
class AccountPassword implements ValidationRule
{
    public const HINT = 'At least 8 characters with uppercase and lowercase letters and a number. No spaces or special characters.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be text.');

            return;
        }

        if (preg_match('/\s/', $value)) {
            $fail('The :attribute must not contain spaces.');
        } elseif (preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The :attribute must not contain special characters, only letters and numbers.');
        } elseif (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters.');
        } elseif (! preg_match('/[a-z]/', $value) || ! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must mix uppercase and lowercase letters.');
        } elseif (! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }
    }
}
