<?php

namespace Tests\Unit;

use App\Support\LateReportingPenalty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LateReportingPenaltyTest extends TestCase
{
    #[DataProvider('delayThresholds')]
    public function test_it_returns_the_penalty_for_the_late_reporting_delay(
        int $delayInMinutes,
        int $expectedPenalty,
    ): void {
        $this->assertSame($expectedPenalty, LateReportingPenalty::forDelay($delayInMinutes));
    }

    public static function delayThresholds(): array
    {
        return [
            'no delay' => [0, 0],
            'ten minutes' => [10, 0],
            'eleven minutes' => [11, 100],
            'thirty minutes' => [30, 100],
            'thirty-one minutes' => [31, 200],
            'sixty minutes' => [60, 200],
            'sixty-one minutes' => [61, 300],
            'two hours' => [120, 300],
            'above two hours' => [121, 500],
        ];
    }
}
