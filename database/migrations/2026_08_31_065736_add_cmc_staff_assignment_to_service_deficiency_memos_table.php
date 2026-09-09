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
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->foreignId('cmc_staff_id')
                ->nullable()
                ->after('service_contract_id')
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cmc_staff_id');
        });
    }
};
