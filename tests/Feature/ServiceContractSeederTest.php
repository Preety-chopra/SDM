<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\ContractPenaltyClause;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\Station;
use Database\Seeders\ServiceContractSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceContractSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_includes_real_contracts_with_locations_clauses_and_audit_records(): void
    {
        config()->set('administrator', [
            'name' => 'administrator',
            'email' => 'administrator@example.com',
            'password' => 'Secure Password 123!',
        ]);

        $this->seed();

        $this->assertDatabaseCount('contractors', 10);
        $this->assertDatabaseCount('service_contracts', 10);
        $this->assertDatabaseCount('lines', 16);
        $this->assertDatabaseCount('stations', 289);
        $this->assertDatabaseCount('contract_penalty_clauses', 126);
        $this->assertDatabaseHas('service_contracts', [
            'contract_number' => 'DMRC/TVS-01/2024',
            'version' => 3,
            'status' => 'published',
            'effective_from' => '2025-05-17 00:00:00',
            'effective_to' => '2029-05-16 00:00:00',
            'cmc_staff_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ]);
        $this->assertDatabaseHas('service_contracts', ['contract_number' => 'DMRC/TVS-03/2024', 'status' => 'draft']);
        $this->assertDatabaseHas('service_contracts', ['contract_number' => 'DMRC/WWS-01/2024', 'status' => 'draft']);

        $contract = ServiceContract::query()->where('contract_number', 'DMRC/TVS-01/2024')->sole();
        $this->assertSame('NCES (Nuvision Commercial & Escort Services)', $contract->contractor->name);
        $this->assertSame(7, $contract->lines()->count());
        $this->assertSame(133, $contract->stations()->count());
        $this->assertSame(18, $contract->penaltyClauses()->count());
        $this->assertDatabaseHas('contract_penalty_clauses', [
            'service_contract_id' => $contract->id,
            'serial_number' => 1,
            'description' => 'Poor dress code',
            'calculation_type' => 'maximum',
            'maximum_amount' => '200.00',
            'cap_applicable' => true,
        ]);
        $this->assertSame($contract->line_id, $contract->station->line_id);
        $this->assertTrue($contract->stations->contains('id', $contract->station_id));
        $this->assertTrue($contract->lines->contains('id', $contract->line_id));
        $this->assertSame([], $contract->stations->pluck('line_id')->diff($contract->lines->modelKeys())->all());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contract.seeded',
            'auditable_type' => ServiceContract::class,
            'auditable_id' => $contract->id,
            'actor_id' => null,
        ]);
        $this->assertSame(10, AuditLog::query()->where('event', 'contract.seeded')->count());

        $baggageHandling = ServiceContract::query()->where('contract_number', 'OOMisc-1435')->sole();
        $this->assertSame('baggage_handling', $baggageHandling->service_type->value);
        $this->assertSame(0, $baggageHandling->penaltyClauses()->count());
        $this->assertSame(4, $baggageHandling->stations()->count());
    }

    public function test_rerunning_the_seeder_preserves_edits_locations_clauses_and_audit_logs(): void
    {
        $this->seed(ServiceContractSeeder::class);
        $contract = ServiceContract::query()->where('contract_number', 'DMRC/TVS-01/2024')->sole();
        $contract->update(['title' => 'Locally revised title', 'status' => 'retired', 'version' => 4]);
        $contract->contractor->update(['address' => 'Updated contractor address', 'is_active' => false]);
        $clause = $contract->penaltyClauses()->firstOrFail();
        $clause->update(['base_amount' => '1234.00']);
        $counts = $this->recordCounts();
        $lineIds = $contract->lines->modelKeys();
        $stationIds = $contract->stations->modelKeys();

        $this->seed(ServiceContractSeeder::class);

        $this->assertSame($counts, $this->recordCounts());
        $this->assertSame('Locally revised title', $contract->fresh()->title);
        $this->assertSame('retired', $contract->fresh()->status);
        $this->assertSame(4, $contract->fresh()->version);
        $this->assertSame('Updated contractor address', $contract->contractor->fresh()->address);
        $this->assertFalse($contract->contractor->fresh()->is_active);
        $this->assertSame('1234.00', $clause->fresh()->base_amount);
        $this->assertSame($lineIds, $contract->fresh()->lines->modelKeys());
        $this->assertSame($stationIds, $contract->fresh()->stations->modelKeys());
    }

    public function test_existing_master_records_are_reused_without_overwriting_them(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'NCES (Nuvision Commercial & Escort Services)',
            'address' => 'Existing address',
            'is_active' => false,
        ]);
        $line = Line::query()->create(['code' => '2', 'name' => 'Existing Yellow Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'HKS', 'name' => 'Existing Hauz Khas', 'line_id' => $line->id, 'is_active' => true]);

        $this->seed(ServiceContractSeeder::class);

        $this->assertDatabaseCount('contractors', 10);
        $this->assertDatabaseHas('service_contracts', ['contract_number' => 'DMRC/TVS-01/2024', 'contractor_id' => $contractor->id]);
        $this->assertSame('Existing address', $contractor->fresh()->address);
        $this->assertFalse($contractor->fresh()->is_active);
        $this->assertSame('Existing Yellow Line', $line->fresh()->name);
        $this->assertSame('Existing Hauz Khas', $station->fresh()->name);
    }

    public function test_conflicting_station_lines_roll_back_all_seeded_records_and_audits(): void
    {
        $line = Line::query()->create(['code' => 'CUSTOM', 'name' => 'Custom line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'HKS', 'name' => 'Existing station', 'line_id' => $line->id, 'is_active' => true]);
        $counts = $this->recordCounts();

        try {
            $this->seed(ServiceContractSeeder::class);
            $this->fail('Conflicting station coverage must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'stations' => ['Station HKS belongs to a different line. Reconcile its mapping before seeding.'],
            ], $exception->errors());
        }

        $this->assertSame($counts, $this->recordCounts());
        $this->assertSame($line->id, $station->fresh()->line_id);
    }

    private function recordCounts(): array
    {
        return [
            'contracts' => ServiceContract::query()->count(),
            'contractors' => Contractor::query()->count(),
            'lines' => Line::query()->count(),
            'stations' => Station::query()->count(),
            'clauses' => ContractPenaltyClause::query()->count(),
            'audits' => AuditLog::query()->count(),
        ];
    }
}
