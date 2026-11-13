<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShortfallCountMigrationTest extends TestCase
{
    public function test_upgrade_and_rollback_preserve_existing_short_deployment_deficiencies(): void
    {
        Schema::create('memo_deficiencies', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('remarks')->nullable();
        });
        $deficiency = ['type' => 'short_deployment', 'remarks' => 'Existing incident'];
        DB::table('memo_deficiencies')->insert($deficiency);
        $migration = require database_path('migrations/2026_11_13_113101_add_shortfall_count_to_memo_deficiencies_table.php');

        $migration->up();

        $this->assertDatabaseHas('memo_deficiencies', [...$deficiency, 'shortfall_count' => null]);
        DB::table('memo_deficiencies')->update(['shortfall_count' => 3]);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('memo_deficiencies', 'shortfall_count'));
        $this->assertDatabaseHas('memo_deficiencies', $deficiency);
    }
}
