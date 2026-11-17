<?php

namespace App\Support;

use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class StationOptions
{
    /**
     * @param  Collection<int, Station>  $stations
     * @return Collection<int, array{id: int, label: string}>
     */
    public static function from(Collection $stations): Collection
    {
        $lineOrder = array_flip(Line::DISPLAY_ORDER);

        return $stations
            ->sortBy(function (Station $station) use ($lineOrder): array {
                $lineCode = $station->metroLine?->code;

                return [
                    $lineOrder[$lineCode] ?? count($lineOrder),
                    $lineCode === null,
                    $lineCode,
                    $lineCode === null ? PHP_INT_MAX : ($station->sequence_no ?? PHP_INT_MAX),
                ];
            })
            ->map(function (Station $station): array {
                $lineCode = $station->metroLine?->code;

                return [
                    'id' => $station->id,
                    'label' => $lineCode === null ? $station->name : Str::start($lineCode, 'L-').' '.$station->name,
                ];
            });
    }
}
