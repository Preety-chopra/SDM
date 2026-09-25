<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memo_deficiencies', function (Blueprint $table): void {
            $table->string('type', 100)->change();
        });
    }

    public function down(): void
    {
        $legacyTypes = [
            'late_reporting',
            'dress_code_or_instructions',
            'misbehavior',
            'cash_check',
            'other',
        ];

        if (DB::table('memo_deficiencies')->whereNotIn('type', $legacyTypes)->exists()) {
            throw new LogicException('Cannot restore the legacy deficiency enum while SSC clause deficiencies exist.');
        }

        Schema::table('memo_deficiencies', function (Blueprint $table) use ($legacyTypes): void {
            $table->enum('type', $legacyTypes)->change();
        });
    }
};
