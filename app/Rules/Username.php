<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match("/\A[\pL\pM\pN](?:[\pL\pM\pN .'-]*[\pL\pM\pN])?\z/u", $value) !== 1) {
            $fail('The :attribute may only contain letters, numbers, spaces, periods, apostrophes, and hyphens.');
        }
    }
}
