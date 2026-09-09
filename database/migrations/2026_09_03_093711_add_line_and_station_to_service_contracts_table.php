<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->foreignId('line_id')->nullable()->after('contractor_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->after('line_id')->constrained('stations')->restrictOnDelete();
            $table->index(['station_id', 'contractor_id', 'service_type', 'status'], 'contracts_station_service_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->dropIndex('contracts_station_service_status_index');
            $table->dropConstrainedForeignId('station_id');
            $table->dropConstrainedForeignId('line_id');
        });
    }
};
