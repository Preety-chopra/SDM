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
            $table->string('current_stage', 50)->nullable()->after('status')->index();
        });

        Schema::table('memo_approvals', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sequence')->nullable()->after('role')->index();
            $table->foreignId('assigned_to')->nullable()->after('sequence')->constrained('users')->nullOnDelete();
            $table->string('assigned_name')->nullable()->after('assigned_to');
            $table->string('assigned_employee_number', 100)->nullable()->after('assigned_name');
            $table->foreignId('acted_by')->nullable()->after('decision')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memo_approvals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acted_by');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['sequence', 'assigned_name', 'assigned_employee_number']);
        });

        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropColumn('current_stage');
        });
    }
};
