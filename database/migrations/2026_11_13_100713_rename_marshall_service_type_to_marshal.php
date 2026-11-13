<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameServiceType('marshall', 'marshal');
    }

    public function down(): void
    {
        $this->renameServiceType('marshal', 'marshall');
    }

    private function renameServiceType(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            DB::table('service_deficiency_memos')->where('service_type', $from)->update(['service_type' => $to]);
            DB::table('service_contracts')->where('service_type', $from)->update(['service_type' => $to]);
        });
    }
};
