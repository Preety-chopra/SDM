<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->string('code', 30)->unique()->after('id');
            $table->string('name', 100)->after('code');
            $table->boolean('is_active')->default(true)->index()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['code', 'name', 'is_active']);
        });
    }
};
