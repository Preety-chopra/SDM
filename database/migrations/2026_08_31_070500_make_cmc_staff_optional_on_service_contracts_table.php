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
            $table->foreignId('cmc_staff_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->foreignId('cmc_staff_id')->nullable(false)->change();
        });
    }
};
