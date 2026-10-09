<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('service_contracts')
            ->select(['id', 'line_id', 'station_id'])
            ->orderBy('id')
            ->each(function (object $contract): void {
                if ($contract->line_id !== null) {
                    DB::table('service_contract_line')->insertOrIgnore([
                        'service_contract_id' => $contract->id,
                        'line_id' => $contract->line_id,
                    ]);
                }

                if ($contract->station_id !== null) {
                    DB::table('service_contract_station')->insertOrIgnore([
                        'service_contract_id' => $contract->id,
                        'station_id' => $contract->station_id,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The following schema migration removes the pivot tables during a full rollback.
    }
};
