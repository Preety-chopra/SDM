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
        Schema::create('memo_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('sequence');
            $table->string('stage', 50)->index();
            $table->string('role', 50)->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_name');
            $table->string('assigned_employee_number', 100)->nullable();
            $table->string('decision', 30)->default('pending')->index();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_employee_number', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'round', 'sequence']);
            $table->index(['memo_id', 'decision', 'sequence']);
        });

        Schema::create('memo_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('version');
            $table->string('source_stage', 50);
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('negative_points')->nullable();
            $table->text('points_justification')->nullable();
            $table->string('staff_action', 30)->nullable();
            $table->text('action_justification')->nullable();
            $table->date('bill_month')->nullable();
            $table->decimal('monthly_bill_value', 14, 2)->nullable();
            $table->decimal('proposed_total', 14, 2)->default(0);
            $table->decimal('cap_percentage', 7, 3)->nullable();
            $table->boolean('cap_warning')->default(false);
            $table->boolean('cap_acknowledged')->default(false);
            $table->boolean('administrative_review_flag')->default(false);
            $table->string('status', 30)->default('proposed')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'version']);
            $table->index(['memo_id', 'round', 'status']);
        });

        Schema::create('memo_assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_penalty_clause_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('clause_serial_number');
            $table->text('clause_description');
            $table->string('calculation_type', 40);
            $table->decimal('base_amount', 14, 2)->nullable();
            $table->decimal('maximum_amount', 14, 2)->nullable();
            $table->boolean('cap_applicable')->default(false);
            $table->json('calculation_inputs')->nullable();
            $table->decimal('suggested_amount', 14, 2)->nullable();
            $table->decimal('proposed_amount', 14, 2);
            $table->text('justification');
            $table->timestamps();
        });

        Schema::create('memo_penalty_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->foreignId('memo_assessment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->date('issued_at');
            $table->date('objection_due_at')->index();
            $table->text('delivery_details');
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();

            $table->unique(['memo_id', 'revision']);
        });

        Schema::create('memo_objections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->foreignId('memo_penalty_letter_id')->constrained()->restrictOnDelete();
            $table->date('received_at');
            $table->text('remarks');
            $table->string('original_name')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('memo_penalty_letter_id');
        });

        Schema::create('memo_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->unique()->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->date('bill_month')->index();
            $table->string('bill_reference', 100);
            $table->date('deducted_at');
            $table->decimal('amount', 14, 2);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_deductions');
        Schema::dropIfExists('memo_objections');
        Schema::dropIfExists('memo_penalty_letters');
        Schema::dropIfExists('memo_assessment_items');
        Schema::dropIfExists('memo_assessments');
        Schema::dropIfExists('memo_workflow_steps');
    }
};
