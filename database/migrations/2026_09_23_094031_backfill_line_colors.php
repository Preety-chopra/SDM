<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('lines')->select(['id', 'code', 'name'])->orderBy('id')->get()
            ->each(function (object $line): void {
                $identity = Str::upper($line->code.' '.$line->name);
                $color = match (true) {
                    Str::contains($identity, 'YELLOW') => '#ffc107',
                    Str::contains($identity, 'BLUE') => '#0d6efd',
                    Str::contains($identity, 'GREEN') => '#198754',
                    Str::contains($identity, 'VIOLET') => '#6610f2',
                    Str::contains($identity, 'PINK') => '#EE9ACD',
                    Str::contains($identity, 'MAGENTA') => '#F75CF7',
                    Str::contains($identity, ['AEL', 'AIRPORT', 'GREY', 'GRAY']) => '#A4A4A4',
                    Str::contains($identity, 'RED') => '#CD5356',
                    default => '#A4A4A4',
                };

                DB::table('lines')->where('id', $line->id)->update(['color' => $color]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lines')->update(['color' => '#A4A4A4']);
    }
};
