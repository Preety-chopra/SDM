<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

final class MasterDataRepository implements MasterDataRepositoryInterface
{
    public function lines(bool $activeOnly = false): Collection
    {
        return Line::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }

    public function stations(bool $activeOnly = false): Collection
    {
        return Station::query()->with('metroLine:id,code,name,is_active')->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }

    public function contractors(bool $activeOnly = false): Collection
    {
        return Contractor::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }

    public function saveLine(?Line $line, array $attributes): Line
    {
        $line ??= new Line;
        $line->fill($attributes)->save();

        return $line->refresh();
    }

    public function saveStation(?Station $station, array $attributes): Station
    {
        $station ??= new Station;
        $station->fill($attributes)->save();

        return $station->refresh()->load('metroLine');
    }

    public function saveContractor(?Contractor $contractor, array $attributes): Contractor
    {
        $contractor ??= new Contractor;
        $contractor->fill($attributes)->save();

        return $contractor->refresh();
    }
}
