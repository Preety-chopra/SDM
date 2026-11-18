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
        Schema::create('historical_sdm_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_file');
            $table->char('source_sha256', 64);
            $table->string('source_sheet', 100);
            $table->unsignedInteger('row_count');
            $table->json('summary');
            $table->timestamps();
            $table->unique(['contractor_id', 'source_sha256', 'source_sheet'], 'historical_sdm_import_source_unique');
        });

        Schema::create('historical_sdms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('historical_sdm_imports')->restrictOnDelete();
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->foreignId('outsourced_staff_id')->nullable()->constrained('outsourced_staff')->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('source_usn', 100)->nullable();
            $table->string('source_date', 100)->nullable();
            $table->date('incident_date')->nullable();
            $table->string('memo_number')->nullable();
            $table->string('source_line', 30)->nullable();
            $table->string('source_station', 100)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_number', 100)->nullable();
            $table->text('aadhaar_number')->nullable();
            $table->text('discrepancies')->nullable();
            $table->text('recommended_action')->nullable();
            $table->decimal('contractor_penalty', 14, 2)->nullable();
            $table->string('cp_number')->nullable();
            $table->decimal('negative_points', 10, 2)->nullable();
            $table->json('import_issues');
            $table->timestamps();
            $table->unique(['import_id', 'source_row']);
            $table->index(['contractor_id', 'incident_date']);
            $table->index(['employee_number', 'incident_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_sdms');
        Schema::dropIfExists('historical_sdm_imports');
    }
};
