<?php

namespace App\Support;

use App\Enums\DeficiencyType;

final class NegativePointCatalog
{
    public static function for(DeficiencyType $deficiency): ?int
    {
        return match ($deficiency) {
            DeficiencyType::DressCode,
            DeficiencyType::MobilePhoneOnDuty,
            DeficiencyType::ImproperCleanliness,
            DeficiencyType::LateReporting,
            DeficiencyType::LossOfLoginIdOrCard => 0,
            DeficiencyType::BreachOfInstructions,
            DeficiencyType::BusinessRulesViolation => 3,
            DeficiencyType::PublicComplaint,
            DeficiencyType::Misbehavior,
            DeficiencyType::ContractTermsViolation,
            DeficiencyType::PersonalCashOrMalpractice => 5,
            DeficiencyType::PossessionOfLiveTickets,
            DeficiencyType::UndervaluedTickets,
            DeficiencyType::Overcharging => 10,
            default => null,
        };
    }

    public static function forCashMismatch(float $differenceAmount): int
    {
        $absoluteDifference = abs($differenceAmount);

        return match (true) {
            $absoluteDifference <= 50 => 0,
            $absoluteDifference < 100 => 3,
            default => 5,
        };
    }

    public static function forHiddenCash(?float $hiddenCashAmount): int
    {
        return $hiddenCashAmount !== null && $hiddenCashAmount > 0 ? 10 : 0;
    }
}
