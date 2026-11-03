<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Line;
use App\Models\Station;
use Database\Seeders\StationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_seeding_creates_required_lines_and_retains_interchange_reference_data(): void
    {
        $this->seed(StationSeeder::class);

        $this->assertDatabaseCount('lines', 16);
        $this->assertDatabaseCount('stations', 289);
        $this->assertDatabaseCount('service_contracts', 0);
        $yellow = Line::query()->where('code', '2')->sole();
        $magenta = Line::query()->where('code', '8')->sole();
        $this->assertDatabaseHas('stations', [
            'code' => 'HKS', 'name' => 'HAUZ KHAS', 'station_id' => 54,
            'line_id' => $yellow->id, 'line_code' => '2',
            'station_name' => 'HAUZ KHAS', 'station_code' => 'HKS', 'sequence_no' => 25,
        ]);
        $this->assertDatabaseHas('stations', [
            'code' => 'HKS-243', 'station_id' => 243, 'line_id' => $magenta->id,
            'line_code' => '8', 'station_code' => 'HKS', 'sequence_no' => 14,
        ]);
        $this->assertSame(289, AuditLog::query()->where('event', 'station.created')->count());
    }

    public function test_reseeding_stations_reuses_line_ids_and_preserves_existing_records(): void
    {
        $line = Line::query()->create(['code' => '2', 'name' => 'Existing Yellow Line', 'is_active' => false]);
        $station = Station::query()->create(['code' => 'HKS', 'name' => 'Existing Hauz Khas', 'line_id' => $line->id, 'is_active' => false]);
        $this->seed(StationSeeder::class);
        $auditCount = AuditLog::query()->count();

        $this->seed(StationSeeder::class);

        $this->assertDatabaseCount('lines', 16);
        $this->assertDatabaseCount('stations', 289);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertSame('Existing Hauz Khas', $station->fresh()->name);
        $this->assertFalse($station->fresh()->is_active);
        $this->assertSame($line->id, $station->fresh()->line_id);
        $this->assertSame('Existing Yellow Line', $line->fresh()->name);
        $this->assertFalse($line->fresh()->is_active);
    }

    public function test_conflicting_line_mapping_rolls_back_lines_stations_and_audit_records(): void
    {
        $line = Line::query()->create(['code' => 'CUSTOM', 'name' => 'Custom line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'HKS', 'name' => 'Existing station', 'line_id' => $line->id]);

        try {
            $this->seed(StationSeeder::class);
            $this->fail('Conflicting station coverage must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stations', $exception->errors());
        }

        $this->assertDatabaseCount('lines', 1);
        $this->assertDatabaseCount('stations', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($line->id, $station->fresh()->line_id);
    }
}
