<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StationManagerOpenMigrationTest extends TestCase
{
    public function test_migration_preserves_existing_memos_and_can_be_rolled_back(): void
    {
        Schema::create('service_deficiency_memos', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number');
            $table->timestamp('submitted_at')->nullable();
        });
        DB::table('service_deficiency_memos')->insert(['serial_number' => 'EXISTING-SDM']);
        $migration = require database_path('migrations/2026_10_30_145453_add_station_manager_opened_at_to_service_deficiency_memos_table.php');

        $migration->up();

        $this->assertDatabaseHas('service_deficiency_memos', [
            'serial_number' => 'EXISTING-SDM',
            'station_manager_opened_at' => null,
        ]);

        $migration->down();

        $this->assertDatabaseHas('service_deficiency_memos', ['serial_number' => 'EXISTING-SDM']);
        $this->assertFalse(Schema::hasColumn('service_deficiency_memos', 'station_manager_opened_at'));
    }
}
