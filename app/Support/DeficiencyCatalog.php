<?php

namespace App\Support;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;

final class DeficiencyCatalog
{
    /** @return array<string, list<array{value: string, label: string, serial_number: int|null}>> */
    public static function grouped(bool $includeLegacyTomDeficiency = false): array
    {
        return collect(SdmType::cases())
            ->mapWithKeys(fn (SdmType $type): array => [$type->value => self::for($type, $includeLegacyTomDeficiency)])
            ->all();
    }

    /** @return list<array{value: string, label: string, serial_number: int|null}> */
    public static function for(SdmType $type, bool $includeLegacyTomDeficiency = false): array
    {
        $types = self::typesFor($type);

        $options = collect(PenaltyClauseTemplates::for($type))
            ->values()
            ->flatMap(function (array $clause, int $index) use ($types, $includeLegacyTomDeficiency): array {
                $deficiency = $types[$index];

                // Multiple deficiencies can share the same contractual clause.
                $labels = match ($deficiency) {
                    DeficiencyType::LateReporting => [
                        $deficiency->value => 'Late reporting',
                        DeficiencyType::LeavingDuty->value => DeficiencyType::LeavingDuty->label(),
                    ],
                    DeficiencyType::PersonalCashOrMalpractice => [
                        DeficiencyType::PersonalCashAtTom->value => DeficiencyType::PersonalCashAtTom->label(),
                        DeficiencyType::IndulgenceInMalpractices->value => DeficiencyType::IndulgenceInMalpractices->label(),
                        ...($includeLegacyTomDeficiency ? [$deficiency->value => $clause['description']] : []),
                    ],
                    default => [$deficiency->value => $clause['description']],
                };

                return array_map(fn (string $value, string $label): array => [
                    'value' => $value,
                    'label' => $label,
                    'serial_number' => $clause['serial_number'],
                ], array_keys($labels), array_values($labels));
            });

        return $options->push([
            'value' => DeficiencyType::Other->value,
            'label' => DeficiencyType::Other->label(),
            'serial_number' => null,
        ])->all();
    }

    /** @return list<string> */
    public static function valuesFor(?SdmType $type, bool $includeLegacyTomDeficiency = false): array
    {
        if ($type === null) {
            return [];
        }

        return array_column(self::for($type, $includeLegacyTomDeficiency), 'value');
    }

    public static function serialNumberFor(SdmType $type, DeficiencyType $deficiency): ?int
    {
        foreach (self::for($type, includeLegacyTomDeficiency: true) as $option) {
            if ($option['value'] === $deficiency->value) {
                return $option['serial_number'];
            }
        }

        return null;
    }

    /** @return list<DeficiencyType> */
    private static function typesFor(SdmType $type): array
    {
        return match ($type) {
            SdmType::BaggageHandling => [],
            SdmType::Cfs, SdmType::WandW, SdmType::Marshal => [
                DeficiencyType::DressCode,
                DeficiencyType::MobilePhoneOnDuty,
                DeficiencyType::ImproperCleanliness,
                DeficiencyType::LateReporting,
                DeficiencyType::Misbehavior,
                DeficiencyType::PublicComplaint,
                DeficiencyType::PossessionOfLiveTickets,
                DeficiencyType::BreachOfInstructions,
                DeficiencyType::ContractTermsViolation,
                DeficiencyType::ShortDeployment,
                DeficiencyType::TheftByContractorStaff,
                DeficiencyType::DamageByContractorStaff,
            ],
            SdmType::Ticketing => [
                DeficiencyType::DressCode,
                DeficiencyType::MobilePhoneOnDuty,
                DeficiencyType::ImproperCleanliness,
                DeficiencyType::LateReporting,
                DeficiencyType::Misbehavior,
                DeficiencyType::PublicComplaint,
                DeficiencyType::BreachOfInstructions,
                DeficiencyType::BusinessRulesViolation,
                DeficiencyType::LossOfLoginIdOrCard,
                DeficiencyType::CashCheck,
                DeficiencyType::UndervaluedTickets,
                DeficiencyType::Overcharging,
                DeficiencyType::ContractTermsViolation,
                DeficiencyType::PersonalCashOrMalpractice,
                DeficiencyType::PossessionOfLiveTickets,
                DeficiencyType::ShortDeployment,
                DeficiencyType::TheftByContractorStaff,
                DeficiencyType::DamageByContractorStaff,
            ],
        };
    }
}
