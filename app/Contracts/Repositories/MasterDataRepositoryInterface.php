<?php

namespace App\Contracts\Repositories;

use App\Models\Contractor;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

interface MasterDataRepositoryInterface
{
    public function lines(bool $activeOnly = false, bool $lock = false): Collection;

    public function stations(bool $activeOnly = false, bool $lock = false): Collection;

    public function contractors(bool $activeOnly = false): Collection;

    public function contractorHasOutsourcedStaff(Contractor $contractor): bool;

    public function saveLine(?Line $line, array $attributes): Line;

    public function saveStation(?Station $station, array $attributes): Station;

    public function saveContractor(?Contractor $contractor, array $attributes): Contractor;
}
