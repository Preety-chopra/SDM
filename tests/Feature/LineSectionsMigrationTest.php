<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class LineSectionsMigrationTest extends TestCase
{
    public function test_unused_schema_rolls_back_without_removing_existing_business_records(): void
    {
        $migration = $this->legacySchema();
        $migration->up();

        // SQLite rebuilds referenced tables when removing a constrained column.
        Schema::withoutForeignKeyConstraints(fn () => $migration->down());

        $this->assertDatabaseHas('lines', ['id' => 1, 'code' => '6']);
        $this->assertDatabaseHas('stations', ['id' => 1, 'line_id' => 1]);
        $this->assertDatabaseHas('service_contracts', ['id' => 1]);
        $this->assertFalse(Schema::hasTable('line_sections'));
        $this->assertFalse(Schema::hasColumn('lines', 'merged_into_line_id'));
        $this->assertFalse(Schema::hasColumn('stations', 'line_section_id'));
    }

    public function test_rollback_refuses_to_discard_section_history(): void
    {
        $migration = $this->legacySchema();
        $migration->up();
        DB::table('line_sections')->insert(['line_id' => 1, 'code' => '6N', 'name' => 'Line-6N', 'station_ids' => '[1]', 'effective_from' => now()]);

        try {
            $migration->down();
            $this->fail('Rollback must retain section history.');
        } catch (LogicException $exception) {
            $this->assertSame('Cannot roll back line sections after section or merge history has been recorded.', $exception->getMessage());
        }

        $this->assertDatabaseHas('line_sections', ['code' => '6N']);
    }

    private function legacySchema(): Migration
    {
        Schema::create('lines', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
        });
        Schema::create('stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained();
        });
        Schema::create('service_contracts', fn (Blueprint $table) => $table->id());
        DB::table('lines')->insert(['id' => 1, 'code' => '6']);
        DB::table('stations')->insert(['id' => 1, 'line_id' => 1]);
        DB::table('service_contracts')->insert(['id' => 1]);

        return require database_path('migrations/2026_11_23_114159_add_versioned_line_sections.php');
    }
}
