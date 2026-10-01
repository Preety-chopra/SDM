<?php

namespace App\Support;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;

final class DeficiencyCatalog
{
    /** @return array<string, list<array{value: string, label: string, serial_number: int|null}>> */
    public static function grouped(): array
    {
        return collect(SdmType::cases())
            ->mapWithKeys(fn (SdmType $type): array => [$type->value => self::for($type)])
            ->all();
    }

    /** @return list<array{value: string, label: string, serial_number: int|null}> */
    public static function for(SdmType $type): array
    {
        $types = self::typesFor($type);

        $options = collect(PenaltyClauseTemplates::for($type))
            ->values()
            ->map(fn (array $clause, int $index): array => [
                'value' => $types[$index]->value,
                'label' => $clause['description'],
                'serial_number' => $clause['serial_number'],
            ]);

        return $options->push([
            'value' => DeficiencyType::Other->value,
            'label' => DeficiencyType::Other->label(),
            'serial_number' => null,
        ])->all();
    }

    /** @return list<string> */
    public static function valuesFor(?SdmType $type): array
    {
        if ($type === null) {
            return [];
        }

        return array_column(self::for($type), 'value');
    }

    public static function serialNumberFor(SdmType $type, DeficiencyType $deficiency): ?int
    {
        foreach (self::for($type) as $option) {
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
            SdmType::Cfs, SdmType::WandW, SdmType::Marshall => [
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
