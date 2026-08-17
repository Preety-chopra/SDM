<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

final class StationRepository implements StationRepositoryInterface
{
    public function active(): Collection
    {
        return Station::query()
            ->select(['id', 'code', 'name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function activeWithLines(): Collection
    {
        return Station::query()
            ->select(['id', 'code', 'name', 'line_id'])
            ->with('line:id,code,name')
            ->where('is_active', true)
            ->whereHas('line', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }
}
