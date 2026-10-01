<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->index(['created_by', 'submitted_at'], 'sdm_creator_submitted_at_index');
            $table->index(['created_by', 'status', 'updated_at'], 'sdm_creator_status_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropIndex('sdm_creator_submitted_at_index');
            $table->dropIndex('sdm_creator_status_updated_at_index');
        });
    }
};
