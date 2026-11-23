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
        Schema::create('line_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->index();
            $table->string('name');
            $table->json('station_ids');
            $table->timestamp('effective_from');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['line_id', 'retired_at']);
        });
        Schema::table('stations', function (Blueprint $table): void {
            $table->foreignId('line_section_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('lines', function (Blueprint $table): void {
            $table->foreignId('merged_into_line_id')->nullable()->constrained('lines')->restrictOnDelete();
        });
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->json('location_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('line_sections')->exists()
            || DB::table('lines')->whereNotNull('merged_into_line_id')->exists()
            || DB::table('service_contracts')->whereNotNull('location_snapshot')->exists()) {
            throw new LogicException('Cannot roll back line sections after section or merge history has been recorded.');
        }

        Schema::table('service_contracts', fn (Blueprint $table) => $table->dropColumn('location_snapshot'));
        Schema::table('lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('merged_into_line_id'));
        Schema::table('stations', fn (Blueprint $table) => $table->dropConstrainedForeignId('line_section_id'));
        Schema::dropIfExists('line_sections');
    }
};
