<?php

namespace App\Support;

use Illuminate\Support\Str;

final class LineColorPalette
{
    public const DEFAULT = '#A4A4A4';

    public static function for(string $code, string $name): string
    {
        $identity = Str::upper($code.' '.$name);

        return match (true) {
            Str::contains($identity, 'YELLOW') => '#ffc107',
            Str::contains($identity, 'BLUE') => '#0d6efd',
            Str::contains($identity, 'GREEN') => '#198754',
            Str::contains($identity, 'VIOLET') => '#6610f2',
            Str::contains($identity, 'PINK') => '#EE9ACD',
            Str::contains($identity, 'MAGENTA') => '#F75CF7',
            Str::contains($identity, ['AEL', 'AIRPORT', 'GREY', 'GRAY']) => self::DEFAULT,
            Str::contains($identity, 'RED') => '#CD5356',
            default => self::DEFAULT,
        };
    }
}
