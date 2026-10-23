<?php

namespace Tests\Unit;

use App\Support\CashMismatchPenalty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CashMismatchPenaltyTest extends TestCase
{
    #[DataProvider('mismatchAmounts')]
    public function test_it_returns_the_default_penalty_for_the_absolute_cash_mismatch(
        float $cashMismatch,
        float $expectedPenalty,
    ): void {
        $this->assertSame($expectedPenalty, CashMismatchPenalty::forMismatch($cashMismatch));
    }

    public function test_it_uses_editable_penalty_amounts(): void
    {
        $amounts = CashMismatchPenalty::defaultAmounts();
        $amounts[1] = 125;

        $this->assertSame(125.0, CashMismatchPenalty::forMismatch(12, $amounts));
    }

    public static function mismatchAmounts(): array
    {
        return [
            'no mismatch' => [0, 0.0],
            'upper nil boundary' => [10, 0.0],
            'decimal above nil boundary' => [10.01, 100.0],
            'negative mismatch' => [-15, 100.0],
            'second slab' => [16, 200.0],
            'third slab' => [26, 300.0],
            'fourth slab' => [36, 400.0],
            'fifth slab' => [46, 500.0],
            'sixth slab' => [56, 600.0],
            'seventh slab' => [66, 700.0],
            'eighth slab' => [76, 800.0],
            'ninth slab' => [86, 900.0],
            'one hundred and above' => [100, 1000.0],
        ];
    }
}
