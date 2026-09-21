<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->index(['created_by', 'created_at'], 'sdm_creator_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropIndex('sdm_creator_created_at_index');
        });
    }
};
