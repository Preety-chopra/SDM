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
        Schema::create('service_contract_line', function (Blueprint $table): void {
            $table->foreignId('service_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->restrictOnDelete();
            $table->primary(['service_contract_id', 'line_id']);
            $table->index(['line_id', 'service_contract_id']);
        });

        Schema::create('service_contract_station', function (Blueprint $table): void {
            $table->foreignId('service_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->primary(['service_contract_id', 'station_id']);
            $table->index(['station_id', 'service_contract_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_contract_station');
        Schema::dropIfExists('service_contract_line');
    }
};
