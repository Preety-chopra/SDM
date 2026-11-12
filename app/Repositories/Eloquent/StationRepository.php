<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class StationRepository implements StationRepositoryInterface
{
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
            ->select(['id', 'code', 'name', 'line_id'])
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
