<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table) {
            $table->time('incident_time')->nullable()->after('incident_date');
        });
    }

    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table) {
            $table->dropColumn('incident_time');
        });
    }
};
