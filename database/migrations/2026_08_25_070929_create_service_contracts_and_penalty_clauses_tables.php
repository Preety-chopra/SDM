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
        Schema::create('service_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('contract_group', 26);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->string('service_type', 50)->index();
            $table->string('contract_number', 100);
            $table->string('title');
            $table->date('effective_from');
            $table->date('effective_to');
            $table->foreignId('cmc_staff_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->string('source_document_name')->nullable();
            $table->string('source_document_path')->nullable();
            $table->string('source_document_mime', 100)->nullable();
            $table->unsignedBigInteger('source_document_size')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_group', 'version']);
            $table->index(['contractor_id', 'service_type', 'status']);
            $table->index(['contractor_id', 'service_type', 'effective_from', 'effective_to'], 'contracts_effective_dates_index');
        });

        Schema::create('contract_penalty_clauses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('serial_number');
            $table->text('description');
            $table->string('calculation_type', 40);
            $table->decimal('base_amount', 14, 2)->nullable();
            $table->decimal('maximum_amount', 14, 2)->nullable();
            $table->boolean('cap_applicable')->default(false)->index();
            $table->json('parameters')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['service_contract_id', 'serial_number'], 'contract_clause_serial_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_penalty_clauses');
        Schema::dropIfExists('service_contracts');
    }
};
