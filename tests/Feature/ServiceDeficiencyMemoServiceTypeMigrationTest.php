<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ServiceDeficiencyMemoServiceTypeMigrationTest extends TestCase
{
    public function test_upgrade_accepts_new_service_types_and_preserves_existing_memos(): void
    {
        $migration = $this->legacySchema();
        DB::table('service_deficiency_memos')->insert([
            ['service_type' => 'token_vending', 'serial_number' => 'TVS-OLD'],
            ['service_type' => 'customer_facilitation', 'serial_number' => 'CFA-OLD'],
        ]);

        $migration->up();

        DB::table('service_deficiency_memos')->insert([
            ['service_type' => 'watch_and_ward', 'serial_number' => 'W&W-NEW'],
            ['service_type' => 'marshall', 'serial_number' => 'MAR-NEW'],
        ]);
        $this->assertSame([
            'TVS-OLD' => 'token_vending',
            'CFA-OLD' => 'customer_facilitation',
            'W&W-NEW' => 'watch_and_ward',
            'MAR-NEW' => 'marshall',
        ], DB::table('service_deficiency_memos')->orderBy('id')->pluck('service_type', 'serial_number')->all());
    }

    public function test_upgrade_preserves_service_type_and_serial_number_uniqueness(): void
    {
        $migration = $this->legacySchema();
        DB::table('service_deficiency_memos')->insert(['service_type' => 'token_vending', 'serial_number' => 'TVS-OLD']);

        $migration->up();

        $this->expectException(QueryException::class);
        DB::table('service_deficiency_memos')->insert(['service_type' => 'token_vending', 'serial_number' => 'TVS-OLD']);
    }

    public function test_rollback_restores_the_legacy_enum_and_preserves_legacy_memos(): void
    {
        $migration = $this->legacySchema();
        $migration->up();
        DB::table('service_deficiency_memos')->insert(['service_type' => 'customer_facilitation', 'serial_number' => 'CFA-OLD']);

        $migration->down();

        $this->assertDatabaseHas('service_deficiency_memos', ['service_type' => 'customer_facilitation', 'serial_number' => 'CFA-OLD']);
        $this->expectException(QueryException::class);
        DB::table('service_deficiency_memos')->insert(['service_type' => 'watch_and_ward', 'serial_number' => 'W&W-NEW']);
    }

    #[TestWith(['watch_and_ward', null])]
    #[TestWith(['marshall', '2026-10-27 17:22:00'])]
    public function test_rollback_refuses_to_truncate_new_service_types_including_deleted_memos(string $serviceType, ?string $deletedAt): void
    {
        $migration = $this->legacySchema();
        $migration->up();
        DB::table('service_deficiency_memos')->insert([
            'service_type' => $serviceType,
            'serial_number' => 'NEW-TYPE',
            'deleted_at' => $deletedAt,
        ]);

        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard service types used by existing memos.');
        } catch (LogicException $exception) {
            $this->assertSame('Cannot restore the legacy service type enum while newer service types exist.', $exception->getMessage());
        }

        $this->assertDatabaseHas('service_deficiency_memos', ['service_type' => $serviceType, 'serial_number' => 'NEW-TYPE']);
    }

    private function legacySchema(): Migration
    {
        Schema::create('service_deficiency_memos', function (Blueprint $table): void {
            $table->id();
            $table->enum('service_type', ['token_vending', 'customer_facilitation']);
            $table->string('serial_number', 100)->nullable();
            $table->softDeletes();
            $table->unique(['service_type', 'serial_number']);
        });

        return require database_path('migrations/2026_10_27_172847_expand_service_type_on_service_deficiency_memos_table.php');
    }
}
