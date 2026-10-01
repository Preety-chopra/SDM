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
            $table->boolean('is_urgent')->default(false)->index()->after('serial_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table) {
            $table->dropIndex(['is_urgent']);
            $table->dropColumn('is_urgent');
        });
    }
};
