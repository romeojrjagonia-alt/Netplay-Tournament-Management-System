<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Philippine mobile number: 11 digits starting with 09, e.g. 09123456789.
 * Spaces and dashes (as in "0912 345 6789") are tolerated, anything else is not.
 */
class PhoneNumber implements ValidationRule
{
    public const LENGTH = 11;

    public const HINT = 'Enter an 11-digit mobile number starting with 09, e.g. 09123456789.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^09\d{9}$/', preg_replace('/[\s-]/', '', $value))) {
            $fail('The :attribute must be an 11-digit mobile number starting with 09, e.g. 09123456789.');
        }
    }
}
