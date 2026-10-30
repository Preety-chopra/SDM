<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LineManagerOpenMigrationTest extends TestCase
{
    public function test_migration_and_rollback_preserve_existing_memos_and_sm_open_times(): void
    {
        Schema::create('service_deficiency_memos', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number');
            $table->timestamp('station_manager_opened_at')->nullable();
        });
        $existing = ['serial_number' => 'EXISTING-SDM', 'station_manager_opened_at' => '2026-10-30 10:00:00'];
        DB::table('service_deficiency_memos')->insert($existing);
        $migration = require database_path('migrations/2026_10_30_151014_add_line_manager_opened_at_to_service_deficiency_memos_table.php');

        $migration->up();

        $this->assertDatabaseHas('service_deficiency_memos', [...$existing, 'line_manager_opened_at' => null]);

        $migration->down();

        $this->assertDatabaseHas('service_deficiency_memos', $existing);
        $this->assertFalse(Schema::hasColumn('service_deficiency_memos', 'line_manager_opened_at'));
    }
}
