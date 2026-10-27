<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->string('service_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $legacyTypes = ['token_vending', 'customer_facilitation'];

        if (DB::table('service_deficiency_memos')->whereNotIn('service_type', $legacyTypes)->exists()) {
            throw new LogicException('Cannot restore the legacy service type enum while newer service types exist.');
        }

        Schema::table('service_deficiency_memos', function (Blueprint $table) use ($legacyTypes): void {
            $table->enum('service_type', $legacyTypes)->change();
        });
    }
};
