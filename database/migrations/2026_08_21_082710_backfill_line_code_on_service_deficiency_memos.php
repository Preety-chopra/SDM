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
        $lineCodesByStation = DB::table('stations')
            ->join('lines', 'lines.id', '=', 'stations.line_id')
            ->pluck('lines.code', 'stations.id');

        foreach ($lineCodesByStation as $stationId => $lineCode) {
            DB::table('service_deficiency_memos')
                ->where('station_id', $stationId)
                ->whereNull('line_code')
                ->update(['line_code' => $lineCode]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: the original line code cannot be distinguished from later snapshots.
    }
};
