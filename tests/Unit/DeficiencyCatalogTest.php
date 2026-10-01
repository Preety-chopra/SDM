<?php

namespace Tests\Unit;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Support\DeficiencyCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeficiencyCatalogTest extends TestCase
{
    #[DataProvider('sdmTypes')]
    public function test_every_catalogued_deficiency_maps_to_its_type_specific_penalty_clause(SdmType $sdmType): void
    {
        foreach (DeficiencyCatalog::for($sdmType) as $option) {
            $deficiency = DeficiencyType::from($option['value']);

            $this->assertSame(
                $option['serial_number'],
                DeficiencyCatalog::serialNumberFor($sdmType, $deficiency),
            );
        }
    }

    public static function sdmTypes(): array
    {
        return array_map(
            fn (SdmType $sdmType): array => [$sdmType],
            SdmType::cases(),
        );
    }
}
