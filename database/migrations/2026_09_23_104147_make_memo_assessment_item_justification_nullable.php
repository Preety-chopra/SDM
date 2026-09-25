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
        Schema::table('memo_assessment_items', function (Blueprint $table): void {
            $table->text('justification')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('memo_assessment_items')
            ->whereNull('justification')
            ->update(['justification' => '']);

        Schema::table('memo_assessment_items', function (Blueprint $table): void {
            $table->text('justification')->nullable(false)->change();
        });
    }
};
