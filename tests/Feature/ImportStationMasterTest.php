<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportStationMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_keeps_every_reference_column_and_preserves_existing_station_links(): void
    {
        $this->referenceTable();
        $line = Line::query()->create(['code' => '7', 'name' => 'Pink Line']);
        $station = Station::query()->create(['code' => 'AHRM', 'name' => 'Old name', 'is_active' => false]);
        $user = User::factory()->create(['station_id' => $station->id]);
        DB::table('station_master')->insert($this->referenceRow());

        $this->artisan('stations:import-master')->assertSuccessful();

        $this->assertDatabaseHas('stations', [
            'id' => $station->id, 'code' => 'AHRM', 'name' => 'Ashram',
            'line_id' => $line->id, 'is_active' => false, ...$this->referenceRow(),
        ]);
        $this->assertSame($station->id, $user->refresh()->station_id);
        $audit = AuditLog::query()->sole();
        $this->assertSame('station.master_imported', $audit->event);
        $this->assertSame($station->id, $audit->auditable_id);
        $this->assertSame('Old name', $audit->before_values['name']);
        $this->assertSame('Ashram', $audit->after_values['name']);
    }

    public function test_import_retains_interchanges_and_blank_codes_as_separate_rows(): void
    {
        $this->referenceTable();
        $yellow = Line::query()->create(['code' => '2', 'name' => 'Yellow Line']);
        $magenta = Line::query()->create(['code' => '8', 'name' => 'Magenta Line']);
        $existing = Station::query()->create(['code' => 'HKS', 'name' => 'Hauz Khas', 'line_id' => $yellow->id]);
        DB::table('station_master')->insert([
            $this->referenceRow(['station_id' => 54, 'station_code' => 'HKS', 'station_name' => 'HAUZ KHAS', 'line_code' => '2']),
            $this->referenceRow(['station_id' => 243, 'station_code' => 'HKS', 'station_name' => 'HAUZ KHAS', 'line_code' => '8']),
            $this->referenceRow(['station_id' => 270, 'station_code' => null, 'station_name' => 'Pulbangash', 'line_code' => '8']),
            $this->referenceRow(['station_id' => 271, 'station_code' => '', 'station_name' => 'Other station', 'line_code' => '8']),
        ]);

        $this->artisan('stations:import-master')->assertSuccessful();

        $this->assertDatabaseCount('stations', 4);
        $this->assertDatabaseHas('stations', ['id' => $existing->id, 'station_id' => 54, 'code' => 'HKS']);
        $this->assertDatabaseHas('stations', ['station_id' => 243, 'station_code' => 'HKS', 'line_id' => $magenta->id]);
        $this->assertDatabaseHas('stations', ['station_id' => 270, 'station_code' => null]);
        $this->assertDatabaseHas('stations', ['station_id' => 271, 'station_code' => '']);
        $this->assertSame(4, Station::query()->distinct()->count('code'));
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_reimport_updates_reference_values_without_duplicates_or_noop_audits(): void
    {
        $this->referenceTable();
        Line::query()->create(['code' => '7', 'name' => 'Pink Line']);
        DB::table('station_master')->insert($this->referenceRow());
        $this->artisan('stations:import-master')->assertSuccessful();
        $station = Station::query()->sole();
        $this->artisan('stations:import-master')->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', 1);
        DB::table('station_master')->update(['station_name' => 'Updated Ashram', 'sequence_no' => 31]);

        $this->artisan('stations:import-master')->assertSuccessful();

        $this->assertDatabaseCount('stations', 1);
        $this->assertDatabaseHas('stations', ['id' => $station->id, 'name' => 'Updated Ashram', 'station_name' => 'Updated Ashram', 'sequence_no' => 31]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_unknown_reference_line_rolls_back_all_station_changes_and_audits(): void
    {
        $this->referenceTable();
        Line::query()->create(['code' => '7', 'name' => 'Pink Line']);
        DB::table('station_master')->insert([
            $this->referenceRow(),
            $this->referenceRow(['station_id' => 212, 'line_code' => 'UNKNOWN']),
        ]);

        $this->artisan('stations:import-master')->assertFailed();

        $this->assertDatabaseCount('stations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_reference_table_fails_without_modifying_stations(): void
    {
        $this->artisan('stations:import-master')->assertFailed();

        $this->assertDatabaseCount('stations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function referenceTable(): void
    {
        Schema::create('station_master', function (Blueprint $table): void {
            $table->integer('station_id')->primary();
            $table->string('line_code', 20)->nullable();
            $table->string('station_name', 150)->nullable();
            $table->string('station_code', 20)->nullable();
            $table->integer('sequence_no')->nullable();
        });
    }

    private function referenceRow(array $overrides = []): array
    {
        return array_replace([
            'station_id' => 211, 'line_code' => '7', 'station_name' => 'Ashram',
            'station_code' => 'AHRM', 'sequence_no' => 30,
        ], $overrides);
    }
}
