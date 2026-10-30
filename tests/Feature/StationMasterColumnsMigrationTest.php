<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class StationMasterColumnsMigrationTest extends TestCase
{
    public function test_schema_can_be_rolled_back_before_reference_data_is_imported(): void
    {
        $migration = $this->legacySchema();
        DB::table('stations')->insert(['code' => 'APMN']);
        $migration->up();

        $migration->down();

        $this->assertDatabaseHas('stations', ['code' => 'APMN']);
        $this->assertFalse(Schema::hasColumn('stations', 'station_id'));
    }

    public function test_rollback_refuses_to_discard_imported_reference_data(): void
    {
        $migration = $this->legacySchema();
        $migration->up();
        DB::table('stations')->insert(['code' => 'APMN', 'station_id' => 127, 'station_code' => 'APMN']);

        try {
            $migration->down();
            $this->fail('Rollback must preserve imported reference data.');
        } catch (LogicException $exception) {
            $this->assertSame('Cannot remove station master columns while imported stations exist.', $exception->getMessage());
        }

        $this->assertDatabaseHas('stations', ['code' => 'APMN', 'station_id' => 127, 'station_code' => 'APMN']);
    }

    private function legacySchema(): Migration
    {
        Schema::create('stations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
        });

        return require database_path('migrations/2026_10_30_125703_add_station_master_columns_to_stations_table.php');
    }
}
