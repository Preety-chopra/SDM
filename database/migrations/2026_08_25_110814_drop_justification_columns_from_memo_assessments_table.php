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
        Schema::table('memo_assessments', function (Blueprint $table) {
            $table->dropColumn(['points_justification', 'action_justification']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memo_assessments', function (Blueprint $table) {
            $table->text('points_justification')->nullable()->after('negative_points');
            $table->text('action_justification')->nullable()->after('staff_action');
        });
    }
};
