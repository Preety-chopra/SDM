<?php

namespace App\Enums;

enum PenaltyCalculationType: string
{
    case Maximum = 'maximum';
    case Fixed = 'fixed';
    case PerDayPerWorker = 'per_day_per_worker';
    case FixedPlusCost = 'fixed_plus_cost';
    case Discretionary = 'discretionary';

    public function label(): string
    {
        return match ($this) {
            self::Maximum => 'Up to maximum amount',
            self::Fixed => 'Fixed amount',
            self::PerDayPerWorker => 'Per day per workman',
            self::FixedPlusCost => 'Fixed amount plus loss/damage cost',
            self::Discretionary => 'Competent authority decision',
        };
    }
}
