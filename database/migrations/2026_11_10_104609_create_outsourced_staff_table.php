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
        Schema::create('outsourced_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->string('employee_id', 50)->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('aadhaar_number')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('designation')->nullable();
            $table->string('entry_permit_number', 100)->nullable();
            $table->string('verification_status', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['contractor_id', 'is_active', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Export staff records before rolling this migration back on a populated database.
        Schema::dropIfExists('outsourced_staff');
    }
};
