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
        Schema::create('aft_cash_check_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_number')->nullable()->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('creator_name');
            $table->string('creator_employee_number', 100)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedBigInteger('current_step_id')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });
        Schema::create('aft_cash_check_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('aft_cash_check_reports')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('check_date')->nullable();
            $table->foreignId('station_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('station_code', 100)->nullable();
            $table->string('station_name')->nullable();
            $table->string('line_code', 100)->nullable();
            $table->string('check_type', 100)->nullable();
            $table->string('tom_number', 100)->nullable();
            $table->time('check_time')->nullable();
            $table->string('operator_name')->nullable();
            $table->string('employee_number', 100)->nullable()->index();
            $table->string('employer', 100)->nullable();
            $table->string('eos_number', 100)->nullable();
            foreach (['cash_as_per_eos', 'actual_cash', 'difference_amount', 'hidden_amount', 'cash_deposit_at_station', 'penalty_amount'] as $column) {
                $table->decimal($column, 14, 2)->nullable();
            }
            $table->string('cash_status', 10)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('cumulative_negative_points')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'sequence']);
        });
        Schema::create('aft_cash_check_report_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('aft_cash_check_reports')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('round')->default(1);
            $table->string('stage', 50);
            $table->string('role', 100);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('assigned_name')->nullable();
            $table->string('assigned_employee_number', 100)->nullable();
            $table->string('decision', 30)->default('pending');
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_employee_number', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'sequence']);
            $table->index(['assigned_to', 'decision']);
        });
        Schema::table('aft_cash_check_reports', function (Blueprint $table): void {
            $table->foreign('current_step_id')->references('id')->on('aft_cash_check_report_steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aft_cash_check_reports', fn (Blueprint $table) => $table->dropForeign(['current_step_id']));
        Schema::dropIfExists('aft_cash_check_report_steps');
        Schema::dropIfExists('aft_cash_check_entries');
        Schema::dropIfExists('aft_cash_check_reports');
    }
};
