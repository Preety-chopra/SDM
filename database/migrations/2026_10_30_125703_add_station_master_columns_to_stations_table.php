<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table): void {
            $table->integer('station_id')->nullable()->unique();
            $table->string('line_code', 20)->nullable();
            $table->string('station_name', 150)->nullable();
            $table->string('station_code', 20)->nullable();
            $table->integer('sequence_no')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('stations')->whereNotNull('station_id')->exists()) {
            throw new LogicException('Cannot remove station master columns while imported stations exist.');
        }

        Schema::table('stations', function (Blueprint $table): void {
            $table->dropUnique(['station_id']);
            $table->dropColumn(['station_id', 'line_code', 'station_name', 'station_code', 'sequence_no']);
        });
    }
};
