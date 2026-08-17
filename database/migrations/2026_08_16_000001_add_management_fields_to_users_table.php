<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_enabled')->default(true)->index()->after('password');
            $table->string('employee_number', 100)->nullable()->unique()->after('email');
            $table->string('phone', 30)->nullable()->after('employee_number');
            $table->string('designation', 100)->nullable()->after('phone');
            $table->foreignId('station_id')->nullable()->after('designation')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('station_id');
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['is_enabled', 'employee_number', 'phone', 'designation']);
        });
    }
};
