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
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->string('status', 50)->default('draft')->change();
            $table->unsignedTinyInteger('workflow_version')->default(1)->after('status')->index();
            $table->foreignId('service_contract_id')->nullable()->after('contractor_id')->constrained()->nullOnDelete();
            $table->foreignId('current_workflow_step_id')->nullable()->after('current_stage')->constrained('memo_workflow_steps')->nullOnDelete();
        });

        Schema::create('memo_staff_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->unique()->constrained('service_deficiency_memos')->cascadeOnDelete();
            $table->string('mobile_number', 30);
            $table->string('method', 30)->default('signature_upload');
            $table->string('status', 30)->default('signed');
            $table->string('original_name');
            $table->string('proof_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_staff_acknowledgements');

        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_workflow_step_id');
            $table->dropConstrainedForeignId('service_contract_id');
            $table->dropColumn('workflow_version');
        });
    }
};
