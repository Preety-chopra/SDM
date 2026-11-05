<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OverchargeAmountMigrationTest extends TestCase
{
    public function test_upgrade_and_rollback_preserve_existing_deficiencies(): void
    {
        Schema::create('memo_deficiencies', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('remarks')->nullable();
        });
        DB::table('memo_deficiencies')->insert(['type' => 'overcharging', 'remarks' => 'Existing incident']);
        $migration = require database_path('migrations/2026_11_05_170000_add_overcharge_amount_to_memo_deficiencies_table.php');

        $migration->up();

        $this->assertDatabaseHas('memo_deficiencies', ['type' => 'overcharging', 'remarks' => 'Existing incident', 'overcharge_amount' => null]);
        DB::table('memo_deficiencies')->update(['overcharge_amount' => '25.75']);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('memo_deficiencies', 'overcharge_amount'));
        $this->assertDatabaseHas('memo_deficiencies', ['type' => 'overcharging', 'remarks' => 'Existing incident']);
    }
}
