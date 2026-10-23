<?php

namespace App\Support;

use App\Enums\PenaltyCalculationType;
use App\Enums\SdmType;

final class PenaltyClauseTemplates
{
    /** @return list<array<string, mixed>> */
    public static function for(SdmType $type): array
    {
        return match ($type) {
            SdmType::Cfs => self::cfa(),
            SdmType::Ticketing => self::tvs(),
            SdmType::WandW => self::watchAndWard(),
            SdmType::Marshall => self::marshal(),
        };
    }

    /** @return list<array<string, mixed>> */
    private static function cfa(): array
    {
        return [
            self::maximum(1, 'Poor dress code', 200, true),
            self::maximum(2, 'Carrying a mobile phone on duty or listening to music on mobile/other devices', 200, true),
            self::maximum(3, 'Improper cleanliness at or near the deputed place', 200, true),
            self::maximum(4, 'Late reporting, leaving duty before scheduled time, or leaving without informing the on-duty SM/SC', 500, true),
            self::maximum(5, 'Misbehaviour and poor customer support', 500, true),
            self::maximum(6, 'Public complaint on any account in a proved case of deficiency', 500, true),
            self::maximum(7, 'Possession of live QR tickets or CSCs', 500, true),
            self::maximum(8, 'Breach of instructions', 500, true),
            self::discretionary(9, 'Violation of terms and conditions of the contract', true),
            self::perDayWorker(10, 'Short deployment of staff', 1000, true),
            self::fixedPlusCost(11, "Theft by contractor's staff", 50000, false),
            self::fixedPlusCost(12, "Damage by contractor's staff", 5000, false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function tvs(): array
    {
        return [
            self::maximum(1, 'Poor dress code', 200, true),
            self::maximum(2, 'Carrying a mobile phone on duty or listening to music on mobile/other devices', 200, true),
            self::maximum(3, 'Improper cleanliness at or near the deputed place', 200, true),
            self::maximum(4, 'Late reporting, leaving duty before scheduled time, or leaving without informing the on-duty SM/SC', 500, true),
            self::maximum(5, 'Misbehaviour and poor customer support', 500, true),
            self::maximum(6, 'Public complaint on any account in a proved case of deficiency', 500, true),
            self::maximum(7, 'Breach of instructions', 500, true),
            self::maximum(8, 'Violation of business rules', 500, true),
            self::fixed(9, 'Loss of login ID or card', 500, true),
            self::maximum(10, 'Cash mismatch during inspection or otherwise', 1000, true, [
                'cash_mismatch_penalty_amounts' => CashMismatchPenalty::defaultAmounts(),
            ]),
            self::fixed(11, 'Issue of undervalued tickets in proved cases', 1000, true),
            self::fixed(12, 'Overcharging in proved cases', 1000, true),
            self::discretionary(13, 'Violation of terms and conditions of the contract', true),
            self::discretionary(14, 'Working at TOM with personal cash or indulgence in malpractices', true),
            self::discretionary(15, 'Possession of live tickets or CSCs', true),
            self::perDayWorker(16, 'Short deployment of staff', 1000, true),
            self::fixedPlusCost(17, "Theft by contractor's staff", 50000, false),
            self::fixedPlusCost(18, "Damage by contractor's staff", 5000, false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function watchAndWard(): array
    {
        return self::personnelServiceClauses(
            'Misbehaviour and poor customer support',
            'Possession of live tokens, CSCs',
        );
    }

    /** @return list<array<string, mixed>> */
    private static function marshal(): array
    {
        return self::personnelServiceClauses(
            'Misbehavior and poor customer support by Marshal/Supervisor',
            'Possession of live tickets/CSCs',
        );
    }

    /** @return list<array<string, mixed>> */
    private static function personnelServiceClauses(string $customerSupportDescription, string $possessionDescription): array
    {
        return [
            self::maximum(1, 'Poor dress code', 200, true),
            self::maximum(2, 'Carrying a mobile phone on duty or listening to music on mobile/other devices', 200, true),
            self::maximum(3, 'Improper cleanliness at or near the deputed place', 200, true),
            self::maximum(4, 'Late reporting, leaving duty before scheduled time, or leaving without informing the on-duty SM/SC', 500, true),
            self::maximum(5, $customerSupportDescription, 500, true),
            self::maximum(6, 'Public complaint on any account in a proved case of deficiency', 500, true),
            self::maximum(7, $possessionDescription, 500, true),
            self::maximum(8, 'Breach of instructions', 500, true),
            self::discretionary(9, 'Violation of terms and conditions of the contract', true),
            self::perDayWorker(10, 'Short deployment of staff', 1000, true),
            self::fixedPlusCost(11, "Theft by contractor's staff", 50000, false),
            self::fixedPlusCost(12, "Damage by contractor's staff", 5000, false),
        ];
    }

    private static function maximum(int $serial, string $description, int $amount, bool $capped, ?array $parameters = null): array
    {
        return self::row($serial, $description, PenaltyCalculationType::Maximum, null, $amount, $capped, $parameters);
    }

    private static function fixed(int $serial, string $description, int $amount, bool $capped): array
    {
        return self::row($serial, $description, PenaltyCalculationType::Fixed, $amount, null, $capped);
    }

    private static function perDayWorker(int $serial, string $description, int $amount, bool $capped): array
    {
        return self::row($serial, $description, PenaltyCalculationType::PerDayPerWorker, $amount, null, $capped, ['inputs' => ['days', 'workers']]);
    }

    private static function fixedPlusCost(int $serial, string $description, int $amount, bool $capped): array
    {
        return self::row($serial, $description, PenaltyCalculationType::FixedPlusCost, $amount, null, $capped, ['inputs' => ['additional_cost']]);
    }

    private static function discretionary(int $serial, string $description, bool $capped): array
    {
        return self::row($serial, $description, PenaltyCalculationType::Discretionary, null, null, $capped);
    }

    private static function row(int $serial, string $description, PenaltyCalculationType $type, ?int $base, ?int $maximum, bool $capped, ?array $parameters = null): array
    {
        return [
            'serial_number' => $serial,
            'description' => $description,
            'calculation_type' => $type->value,
            'base_amount' => $base,
            'maximum_amount' => $maximum,
            'cap_applicable' => $capped,
            'parameters' => $parameters,
            'sort_order' => $serial,
        ];
    }
}
