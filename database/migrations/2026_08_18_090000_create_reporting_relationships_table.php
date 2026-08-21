<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subordinate_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        DB::table('station_hierarchies')->orderBy('id')->each(function (object $hierarchy): void {
            foreach ([
                [$hierarchy->station_manager_id, $hierarchy->line_manager_id],
                [$hierarchy->line_manager_id, $hierarchy->operations_manager_id],
                [$hierarchy->operations_manager_id, $hierarchy->dgm_id],
            ] as [$subordinateId, $supervisorId]) {
                if ($subordinateId && $supervisorId) {
                    DB::table('reporting_relationships')->updateOrInsert(
                        ['subordinate_id' => $subordinateId],
                        ['supervisor_id' => $supervisorId, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_relationships');
    }
};
