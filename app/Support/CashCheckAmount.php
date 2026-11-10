<?php

namespace App\Support;

final class CashCheckAmount
{
    public static function difference(?string $actual, ?string $expected): ?string
    {
        if ($actual === null || $actual === '' || $expected === null || $expected === '') {
            return null;
        }
        $difference = self::cents($actual) - self::cents($expected);

        return ($difference < 0 ? '-' : '').intdiv(abs($difference), 100).'.'.str_pad((string) (abs($difference) % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
