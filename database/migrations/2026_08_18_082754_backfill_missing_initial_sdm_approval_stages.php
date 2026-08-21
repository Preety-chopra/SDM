<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $memos = DB::table('service_deficiency_memos as memos')
                ->join('station_hierarchies as hierarchies', 'hierarchies.station_id', '=', 'memos.station_id')
                ->join('users as station_managers', 'station_managers.id', '=', 'hierarchies.station_manager_id')
                ->join('users as line_managers', 'line_managers.id', '=', 'hierarchies.line_manager_id')
                ->where('memos.status', 'submitted')
                ->where('memos.current_stage', 'manager_operations')
                ->where('station_managers.is_enabled', true)
                ->where('line_managers.is_enabled', true)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('memo_approvals as operations_approvals')
                        ->whereColumn('operations_approvals.memo_id', 'memos.id')
                        ->where('operations_approvals.role', 'manager_operations')
                        ->where('operations_approvals.decision', 'pending')
                        ->whereNull('operations_approvals.acted_at');
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('memo_approvals as initial_approvals')
                        ->whereColumn('initial_approvals.memo_id', 'memos.id')
                        ->whereIn('initial_approvals.role', ['station_manager', 'manager_assistant_manager_line']);
                })
                ->select([
                    'memos.id',
                    'station_managers.id as station_manager_id',
                    'station_managers.name as station_manager_name',
                    'station_managers.employee_number as station_manager_employee_number',
                    'line_managers.id as line_manager_id',
                    'line_managers.name as line_manager_name',
                    'line_managers.employee_number as line_manager_employee_number',
                ])
                ->orderBy('memos.id')
                ->get();

            foreach ($memos as $memo) {
                $timestamp = now();
                $inserted = DB::table('memo_approvals')->insertOrIgnore([
                    [
                        'memo_id' => $memo->id,
                        'role' => 'station_manager',
                        'sequence' => 1,
                        'assigned_to' => $memo->station_manager_id,
                        'assigned_name' => $memo->station_manager_name,
                        'assigned_employee_number' => $memo->station_manager_employee_number,
                        'decision' => 'pending',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                    [
                        'memo_id' => $memo->id,
                        'role' => 'manager_assistant_manager_line',
                        'sequence' => 2,
                        'assigned_to' => $memo->line_manager_id,
                        'assigned_name' => $memo->line_manager_name,
                        'assigned_employee_number' => $memo->line_manager_employee_number,
                        'decision' => 'pending',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                ]);

                if ($inserted === 2) {
                    DB::table('service_deficiency_memos')
                        ->where('id', $memo->id)
                        ->update([
                            'current_stage' => 'station_manager',
                            'updated_at' => $timestamp,
                        ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: removing approval history after officers act would corrupt the audit trail.
    }
};
