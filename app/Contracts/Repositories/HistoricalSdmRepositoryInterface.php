<?php

namespace App\Contracts\Repositories;

use App\Models\HistoricalSdmImport;
use Illuminate\Database\Eloquent\Collection;

interface HistoricalSdmRepositoryInterface
{
    public function findImport(int $contractorId, string $sha256, string $sheet): ?HistoricalSdmImport;

    public function lockContractor(int $id): void;

    public function staff(int $contractorId): Collection;

    public function stations(): Collection;

    public function createImport(array $attributes): HistoricalSdmImport;

    public function insertRecords(array $records): void;
}
