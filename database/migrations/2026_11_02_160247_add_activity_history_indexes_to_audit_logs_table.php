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
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'audit_logs_chronology_index');
            $table->index(['actor_id', 'created_at', 'id'], 'audit_logs_actor_chronology_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_chronology_index');
            $table->dropIndex('audit_logs_chronology_index');
        });
    }
};
