<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class StationRepository implements StationRepositoryInterface
{
    public function paginateForContractMapping(?string $stationSearch): LengthAwarePaginator
    {
        $lineOrder = implode(' ', array_map(fn (int $position): string => 'WHEN ? THEN '.$position, array_keys(Line::DISPLAY_ORDER)));

        return Station::query()
            ->select('stations.*')
            ->leftJoin('lines', 'lines.id', '=', 'stations.line_id')
            ->with('metroLine:id,code,is_active')
            ->when($stationSearch !== null, function (Builder $query) use ($stationSearch): void {
                $name = strtr(mb_strtolower($stationSearch), ['!' => '!!', '%' => '!%', '_' => '!_']);
                $query->whereRaw("LOWER(stations.name) LIKE ? ESCAPE '!'", ['%'.$name.'%']);
            })
            ->orderByRaw('CASE lines.code '.$lineOrder.' ELSE '.count(Line::DISPLAY_ORDER).' END', Line::DISPLAY_ORDER)
            ->orderByRaw('lines.code IS NULL')
            ->orderBy('lines.code')
            ->orderByRaw('stations.sequence_no IS NULL')
            ->orderBy('stations.sequence_no')
            ->orderBy('stations.name')
            ->orderBy('stations.id')
            ->paginate(30);
    }

    public function masterRows(): BaseCollection
    {
        if (! Schema::hasTable('station_master')) {
            throw new RuntimeException('The station_master reference table does not exist.');
        }

        return DB::table('station_master')->orderBy('station_id')->get();
    }

    public function lockForMasterImport(): Collection
    {
        return Station::query()->orderBy('id')->lockForUpdate()->get();
    }

    public function saveImported(Station $station): void
    {
        $station->save();
    }

    public function active(): Collection
    {
        return Station::query()
            ->select(['id', 'code', 'name', 'line_id', 'sequence_no'])
            ->with('metroLine:id,code,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function activeWithLines(): Collection
    {
        return Station::query()
            ->select(['id', 'code', 'name', 'line_id'])
            ->with('metroLine:id,code,name')
            ->where('is_active', true)
            ->whereHas('metroLine', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }
}
