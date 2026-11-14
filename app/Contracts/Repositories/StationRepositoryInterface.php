<?php

namespace App\Contracts\Repositories;

use App\Models\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface StationRepositoryInterface
{
    public function nameById(int $id): ?string;

    public function paginateForContractMapping(?string $stationSearch): LengthAwarePaginator;

    public function active(): Collection;

    public function activeWithLines(): Collection;

    public function masterRows(): BaseCollection;

    public function lockForMasterImport(): Collection;

    public function saveImported(Station $station): void;
}
