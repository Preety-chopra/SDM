<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('line', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 100)->unique();
            $table->string('working_id', 100)->nullable()->unique();
            $table->string('name');
            $table->string('designation', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_deficiency_memos', function (Blueprint $table) {
            $table->id();
            $table->enum('service_type', ['token_vending', 'customer_facilitation']);
            $table->string('serial_number', 50);
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contractor_name');
            $table->string('staff_name');
            $table->string('employee_number', 100)->nullable();
            $table->string('working_id', 100)->nullable();
            $table->date('incident_date');
            $table->text('station_record_brief_remarks')->nullable();
            $table->text('recommended_action')->nullable();
            $table->string('station_seal_path')->nullable();
            $table->timestamp('station_sealed_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'closed', 'cancelled'])
                ->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['service_type', 'serial_number']);
            $table->index(['station_id', 'incident_date']);
            $table->index(['employee_number', 'incident_date']);
            $table->index(['status', 'incident_date']);
        });

        Schema::create('memo_deficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->enum('type', [
                'late_reporting',
                'dress_code_or_instructions',
                'misbehavior',
                'cash_check',
                'other',
            ]);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'type']);
        });

        Schema::create('memo_late_reporting_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_deficiency_id')->unique()->constrained('memo_deficiencies')->cascadeOnDelete();
            $table->dateTime('booked_shift_from');
            $table->dateTime('booked_shift_to');
            $table->dateTime('actual_reporting_at');
            $table->timestamps();
        });

        Schema::create('memo_public_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->string('complaint_serial_number', 100);
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('station_name')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['complaint_serial_number', 'station_id']);
        });

        Schema::create('memo_cash_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->unique()->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->string('tom_ccc_number', 100)->nullable();
            $table->string('end_of_shift_receipt_number', 100)->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable()
                ->comment('Positive is excess; negative is shortage.');
            $table->decimal('hidden_cash_amount', 12, 2)->nullable();
            $table->string('operator_name')->nullable();
            $table->string('operator_signature_path')->nullable();
            $table->string('checking_official_name')->nullable();
            $table->string('checking_official_designation', 100)->nullable();
            $table->string('checking_official_employee_number', 100)->nullable();
            $table->string('checking_official_signature_path')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('memo_office_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->unsignedSmallInteger('row_number')->default(1);
            $table->string('complaint_record')->nullable();
            $table->decimal('excess_short_amount', 12, 2)->nullable();
            $table->string('uniform_violation')->nullable();
            $table->string('behavior')->nullable();
            $table->string('others')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'row_number']);
        });

        Schema::create('memo_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('memo_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->enum('role', [
                'station_manager',
                'manager_assistant_manager_line',
                'manager_operations',
                'asc_tvs_cfs_contracts',
                'dgm_operations',
            ]);
            $table->string('signer_name')->nullable();
            $table->string('signer_employee_number', 100)->nullable();
            $table->string('signature_path')->nullable();
            $table->enum('decision', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['memo_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_approvals');
        Schema::dropIfExists('memo_attachments');
        Schema::dropIfExists('memo_office_records');
        Schema::dropIfExists('memo_cash_checks');
        Schema::dropIfExists('memo_public_complaints');
        Schema::dropIfExists('memo_late_reporting_details');
        Schema::dropIfExists('memo_deficiencies');
        Schema::dropIfExists('service_deficiency_memos');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('stations');
    }
};
