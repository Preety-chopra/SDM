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
        Schema::table('memo_approvals', function (Blueprint $table): void {
            $table->string('assignment_source', 20)->default('workflow')->after('assigned_employee_number');
        });

        Schema::table('memo_workflow_steps', function (Blueprint $table): void {
            $table->string('assignment_source', 20)->default('workflow')->after('assigned_employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memo_approvals', function (Blueprint $table): void {
            $table->dropColumn('assignment_source');
        });

        Schema::table('memo_workflow_steps', function (Blueprint $table): void {
            $table->dropColumn('assignment_source');
        });
    }
};
