<?php

namespace App\Support;

final class LateReportingPenalty
{
    public static function forDelay(int $delayInMinutes): int
    {
        return match (true) {
            $delayInMinutes <= 10 => 0,
            $delayInMinutes <= 30 => 100,
            $delayInMinutes <= 60 => 200,
            $delayInMinutes <= 120 => 300,
            default => 500,
        };
    }
}
