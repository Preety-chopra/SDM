<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match("/\A[\pL\pM](?:[\pL\pM .'-]*[\pL\pM])?\z/u", $value) !== 1) {
            $fail('The :attribute must be a name and may only contain letters, spaces, periods, apostrophes, and hyphens.');
        }
    }
}
