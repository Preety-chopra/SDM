<?php

namespace Tests\Feature;

use App\Enums\SdmType;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\StationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReconcileLocationLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_reports_all_inferred_mappings_without_writes(): void
    {
        $green = Line::query()->create(['code' => 'LEGACY-G', 'name' => 'Green Line']);
        $yellow = Line::query()->create(['code' => 'LEGACY-Y', 'name' => 'Yellow Line']);
        $station = Station::query()->create(['code' => 'APMN', 'name' => 'Local name', 'line_id' => $green->id]);
        Station::query()->create(['code' => 'AHNR', 'name' => 'Adarsh Nagar', 'line_id' => $yellow->id]);

        $this->artisan('locations:reconcile-lines')
            ->expectsOutputToContain('LEGACY-G')
            ->expectsOutputToContain('LEGACY-Y')
            ->expectsOutputToContain('Preview only')
            ->assertSuccessful();

        $this->assertDatabaseCount('lines', 2);
        $this->assertDatabaseCount('stations', 2);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($green->id, $station->fresh()->line_id);
    }

    public function test_apply_reconciles_all_line_references_preserving_station_ids_and_contract_coverage(): void
    {
        $legacy = Line::query()->create(['code' => 'LEGACY-G', 'name' => 'Green Line']);
        $secondLegacy = Line::query()->create(['code' => 'SECOND-G', 'name' => 'Another Green Line']);
        $legacyYellow = Line::query()->create(['code' => 'LEGACY-Y', 'name' => 'Yellow Line']);
        $canonical = Line::query()->create(['code' => '5', 'name' => 'Green Line']);
        $station = Station::query()->create(['code' => 'APMN', 'name' => 'Local name', 'line_id' => $legacy->id, 'is_active' => false]);
        $local = Station::query()->create(['code' => 'LOCAL', 'name' => 'Local station', 'line_id' => $legacy->id]);
        $secondGreen = Station::query()->create(['code' => 'BUSS', 'name' => 'Bahadurgarh City', 'line_id' => $secondLegacy->id]);
        $yellow = Station::query()->create(['code' => 'AHNR', 'name' => 'Adarsh Nagar', 'line_id' => $legacyYellow->id]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $contract = $this->contract($legacy, $station);
        $contract->lines()->sync([$legacy->id, $canonical->id, $secondLegacy->id, $legacyYellow->id]);
        $contract->stations()->sync([$station->id]);

        $this->artisan('locations:reconcile-lines', ['--apply' => true])->assertSuccessful();

        $this->assertSame($canonical->id, $station->fresh()->line_id);
        $this->assertSame('Local name', $station->fresh()->name);
        $this->assertFalse($station->fresh()->is_active);
        $this->assertSame($canonical->id, $local->fresh()->line_id);
        $this->assertSame($canonical->id, $secondGreen->fresh()->line_id);
        $canonicalYellow = Line::query()->where('code', '2')->sole();
        $this->assertSame($canonicalYellow->id, $yellow->fresh()->line_id);
        $this->assertSame($station->id, $user->fresh()->station_id);
        $this->assertSame($canonical->id, $contract->fresh()->line_id);
        $this->assertSame([$canonical->id, $canonicalYellow->id], $contract->fresh()->lines()->orderBy('lines.id')->pluck('lines.id')->all());
        $this->assertSame([$station->id], $contract->fresh()->stations->modelKeys());
        $this->assertModelExists($legacy);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'station.line_reconciled', 'auditable_id' => $station->id, 'actor_id' => null,
        ]);
        $audit = AuditLog::query()->where('event', 'contract.lines_reconciled')->sole();
        $this->assertSame($legacy->id, $audit->before_values['line_id']);
        $this->assertSame($canonical->id, $audit->after_values['line_id']);
        $auditCount = AuditLog::query()->count();

        $this->artisan('locations:reconcile-lines', ['--apply' => true])->assertSuccessful();

        $this->assertSame($auditCount, AuditLog::query()->count());

        $this->seed(StationSeeder::class);
        $this->assertSame($canonical->id, $station->fresh()->line_id);
        $this->assertSame('Local name', $station->fresh()->name);
    }

    public function test_ambiguous_source_line_aborts_without_creating_canonical_lines(): void
    {
        $legacy = Line::query()->create(['code' => 'MIXED', 'name' => 'Mixed line']);
        $station = Station::query()->create(['code' => 'APMN', 'name' => 'Ashok Park', 'line_id' => $legacy->id]);
        Station::query()->create(['code' => 'AHNR', 'name' => 'Adarsh Nagar', 'line_id' => $legacy->id]);

        $this->artisan('locations:reconcile-lines', ['--apply' => true])
            ->expectsOutputToContain('multiple snapshot lines')
            ->assertFailed();

        $this->assertDatabaseCount('lines', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($legacy->id, $station->fresh()->line_id);
    }

    public function test_missing_line_assignment_aborts_without_writes(): void
    {
        Station::query()->create(['code' => 'APMN', 'name' => 'Ashok Park']);

        $this->artisan('locations:reconcile-lines', ['--apply' => true])
            ->expectsOutputToContain('Station APMN has no valid line')
            ->assertFailed();

        $this->assertDatabaseCount('lines', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseHas('stations', ['code' => 'APMN', 'line_id' => null]);
    }

    public function test_swapped_codes_are_remapped_from_original_references_without_cascading(): void
    {
        $first = Line::query()->create(['code' => '2', 'name' => 'Server green']);
        $second = Line::query()->create(['code' => '5', 'name' => 'Server yellow']);
        $green = Station::query()->create(['code' => 'APMN', 'name' => 'Ashok Park', 'line_id' => $first->id]);
        $yellow = Station::query()->create(['code' => 'AHNR', 'name' => 'Adarsh Nagar', 'line_id' => $second->id]);
        $contract = $this->contract($first, $green);
        $contract->lines()->sync([$first->id, $second->id]);

        $this->artisan('locations:reconcile-lines', ['--apply' => true])->assertSuccessful();

        $this->assertSame($second->id, $green->fresh()->line_id);
        $this->assertSame($first->id, $yellow->fresh()->line_id);
        $this->assertSame($second->id, $contract->fresh()->line_id);
        $this->assertSame(2, $contract->fresh()->lines()->count());
        $this->assertSame('Yellow Line', $first->fresh()->name);
        $this->assertSame('Green Line', $second->fresh()->name);
    }

    public function test_unmatched_lines_are_left_unchanged_and_reported(): void
    {
        $legacy = Line::query()->create(['code' => 'UNKNOWN', 'name' => 'Local line']);
        $station = Station::query()->create(['code' => 'LOCAL', 'name' => 'Local', 'line_id' => $legacy->id]);

        $this->artisan('locations:reconcile-lines', ['--apply' => true])
            ->expectsOutputToContain('UNKNOWN')
            ->assertSuccessful();

        $this->assertSame($legacy->id, $station->fresh()->line_id);
        $this->assertDatabaseCount('lines', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_reference_changes_and_new_lines(): void
    {
        $legacy = Line::query()->create(['code' => 'LEGACY-G', 'name' => 'Green Line']);
        $station = Station::query()->create(['code' => 'APMN', 'name' => 'Ashok Park', 'line_id' => $legacy->id]);
        $contract = $this->contract($legacy, $station);
        $contract->lines()->sync([$legacy->id]);
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"') && in_array('contract.lines_reconciled', $query->bindings, true)) {
                throw new RuntimeException('Audit storage failed');
            }
        });

        $this->artisan('locations:reconcile-lines', ['--apply' => true])
            ->expectsOutputToContain('Audit storage failed')
            ->assertFailed();

        $this->assertSame($legacy->id, $station->fresh()->line_id);
        $this->assertSame($legacy->id, $contract->fresh()->line_id);
        $this->assertSame([$legacy->id], $contract->fresh()->lines->modelKeys());
        $this->assertDatabaseCount('lines', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function contract(Line $line, Station $station): ServiceContract
    {
        return ServiceContract::query()->create([
            'contract_group' => '01K00000000000000000000000',
            'contractor_id' => Contractor::query()->create(['name' => 'Test contractor', 'code' => 'TEST'])->id,
            'line_id' => $line->id,
            'station_id' => $station->id,
            'service_type' => SdmType::Ticketing,
            'contract_number' => 'TEST/001',
            'title' => 'Existing contract',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);
    }
}
