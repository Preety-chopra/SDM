<?php

namespace App\Support;

final class CashMismatchPenalty
{
    /** @return list<array{label: string, maximum: float|null}> */
    public static function bands(): array
    {
        return [
            ['label' => 'Up to ₹10', 'maximum' => 10],
            ['label' => 'Above ₹10 to ₹15', 'maximum' => 15],
            ['label' => 'Above ₹15 to ₹25', 'maximum' => 25],
            ['label' => 'Above ₹25 to ₹35', 'maximum' => 35],
            ['label' => 'Above ₹35 to ₹45', 'maximum' => 45],
            ['label' => 'Above ₹45 to ₹55', 'maximum' => 55],
            ['label' => 'Above ₹55 to ₹65', 'maximum' => 65],
            ['label' => 'Above ₹65 to ₹75', 'maximum' => 75],
            ['label' => 'Above ₹75 to ₹85', 'maximum' => 85],
            ['label' => 'Above ₹85 to below ₹100', 'maximum' => 99.99],
            ['label' => '₹100 and above', 'maximum' => null],
        ];
    }

    /** @return list<int> */
    public static function defaultAmounts(): array
    {
        return [0, 100, 200, 300, 400, 500, 600, 700, 800, 900, 1000];
    }

    /** @param array<int, int|float|string|null>|null $amounts */
    public static function forMismatch(float $cashMismatch, ?array $amounts = null): float
    {
        $amounts = array_replace(self::defaultAmounts(), $amounts ?? []);
        $absoluteMismatch = abs($cashMismatch);

        foreach (self::bands() as $index => $band) {
            if ($band['maximum'] === null || $absoluteMismatch <= $band['maximum']) {
                return (float) $amounts[$index];
            }
        }

        return (float) $amounts[array_key_last($amounts)];
    }
}
