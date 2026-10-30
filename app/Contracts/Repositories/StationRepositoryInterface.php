<?php

namespace App\Contracts\Repositories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface StationRepositoryInterface
{
    public function active(): Collection;

    public function activeWithLines(): Collection;

    public function masterRows(): BaseCollection;

    public function lockForMasterImport(): Collection;

    public function saveImported(Station $station): void;
}
