<?php

namespace App\Contracts\Repositories;

use App\Models\Line;
use App\Models\LineSection;
use App\Models\ServiceContract;
use Illuminate\Database\Eloquent\Collection;

interface LineTopologyRepositoryInterface
{
    public function lockLines(): Collection;

    public function stations(array $lineIds, bool $lock = false): Collection;

    public function sections(Line $line): Collection;

    public function sectionCodes(): Collection;

    public function retireSections(array $lineIds): void;

    public function createSection(Line $line, array $data): LineSection;

    public function assignSection(array $stationIds, int $sectionId): void;

    public function merge(Line $target, Collection $lines): void;

    public function affectedContracts(array $lineIds): Collection;

    public function snapshotContract(ServiceContract $contract, array $labels): void;
}
