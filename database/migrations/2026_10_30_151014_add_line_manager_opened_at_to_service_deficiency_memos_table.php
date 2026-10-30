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
        Schema::table('service_deficiency_memos', function (Blueprint $table) {
            $table->timestamp('line_manager_opened_at')->nullable()->after('station_manager_opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table) {
            $table->dropColumn('line_manager_opened_at');
        });
    }
};
