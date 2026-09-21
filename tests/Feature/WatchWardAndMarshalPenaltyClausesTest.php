<?php

namespace Tests\Feature;

use App\Enums\PenaltyCalculationType;
use App\Enums\SdmType;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchWardAndMarshalPenaltyClausesTest extends TestCase
{
    use RefreshDatabase;

    public function test_watch_and_ward_contract_uses_its_documented_penalty_clauses(): void
    {
        $contract = $this->contract(SdmType::WandW, 'W&W/2026/01');

        $this->assertDocumentedClauses($contract, [
            5 => 'Misbehaviour and poor customer support',
            7 => 'Possession of live tokens, CSCs',
        ]);
    }

    public function test_marshal_contract_uses_its_documented_penalty_clauses(): void
    {
        $contract = $this->contract(SdmType::Marshall, 'MAR/2026/01');

        $this->assertDocumentedClauses($contract, [
            5 => 'Misbehavior and poor customer support by Marshal/Supervisor',
            7 => 'Possession of live tickets/CSCs',
        ]);
    }

    /** @param array<int, string> $serviceSpecificDescriptions */
    private function assertDocumentedClauses(ServiceContract $contract, array $serviceSpecificDescriptions): void
    {
        $clauses = $contract->penaltyClauses()->get()->keyBy('serial_number');

        $this->assertCount(12, $clauses);
        $this->assertSame('Poor dress code', $clauses[1]->description);
        $this->assertSame($serviceSpecificDescriptions[5], $clauses[5]->description);
        $this->assertSame($serviceSpecificDescriptions[7], $clauses[7]->description);

        foreach ([1, 2, 3] as $serial) {
            $this->assertSame(PenaltyCalculationType::Maximum, $clauses[$serial]->calculation_type);
            $this->assertSame('200.00', $clauses[$serial]->maximum_amount);
        }

        foreach ([4, 5, 6, 7, 8] as $serial) {
            $this->assertSame(PenaltyCalculationType::Maximum, $clauses[$serial]->calculation_type);
            $this->assertSame('500.00', $clauses[$serial]->maximum_amount);
        }

        $this->assertSame(PenaltyCalculationType::Discretionary, $clauses[9]->calculation_type);
        $this->assertSame(PenaltyCalculationType::PerDayPerWorker, $clauses[10]->calculation_type);
        $this->assertSame('1000.00', $clauses[10]->base_amount);
        $this->assertSame(['inputs' => ['days', 'workers']], $clauses[10]->parameters);
        $this->assertSame(PenaltyCalculationType::FixedPlusCost, $clauses[11]->calculation_type);
        $this->assertSame('50000.00', $clauses[11]->base_amount);
        $this->assertSame(PenaltyCalculationType::FixedPlusCost, $clauses[12]->calculation_type);
        $this->assertSame('5000.00', $clauses[12]->base_amount);

        foreach (range(1, 10) as $serial) {
            $this->assertTrue($clauses[$serial]->cap_applicable);
        }

        $this->assertFalse($clauses[11]->cap_applicable);
        $this->assertFalse($clauses[12]->cap_applicable);
    }

    private function contract(SdmType $serviceType, string $number): ServiceContract
    {
        $contractor = Contractor::query()->create([
            'name' => $serviceType->code().' Contractor',
            'is_active' => true,
        ]);
        $line = Line::query()->create([
            'code' => $serviceType->code().'-LINE',
            'name' => $serviceType->code().' Line',
            'is_active' => true,
        ]);
        $station = Station::query()->create([
            'code' => $serviceType->code().'-STATION',
            'name' => $serviceType->code().' Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $cmcStaff = User::factory()->create();
        $contract = ServiceContract::query()->create([
            'contract_group' => str()->ulid()->toString(),
            'contractor_id' => $contractor->id,
            'line_id' => $line->id,
            'station_id' => $station->id,
            'service_type' => $serviceType->value,
            'contract_number' => $number,
            'title' => $serviceType->label().' Contract',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'cmc_staff_id' => $cmcStaff->id,
        ]);

        $contract->seedPenaltyClauses();

        return $contract;
    }
}
