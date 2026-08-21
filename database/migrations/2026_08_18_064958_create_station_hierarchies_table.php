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
        Schema::create('station_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('station_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('line_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operations_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dgm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('station_hierarchies');
    }
};
