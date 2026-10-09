<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MasterDataService
{
    public function __construct(private MasterDataRepositoryInterface $repository) {}

    public function lines(bool $activeOnly = false): Collection
    {
        return $this->repository->lines($activeOnly);
    }

    public function stations(bool $activeOnly = false): Collection
    {
        return $this->repository->stations($activeOnly);
    }

    public function contractors(bool $activeOnly = false): Collection
    {
        return $this->repository->contractors($activeOnly);
    }

    public function saveLine(?Line $line, array $data): Line
    {
        return DB::transaction(fn () => $this->repository->saveLine($line, $data));
    }

    public function saveStation(?Station $station, array $data): Station
    {
        return DB::transaction(fn () => $this->repository->saveStation($station, $data));
    }

    public function saveContractor(?Contractor $contractor, array $data): Contractor
    {
        return DB::transaction(fn () => $this->repository->saveContractor($contractor, $data));
    }

    public function deleteLine(Line $line): void
    {
        throw_if($line->stations()->exists() || $line->serviceContracts()->exists(), ValidationException::withMessages(['line' => 'This line is assigned to one or more stations or contracts and cannot be deleted.']));
        $line->delete();
    }

    public function deleteStation(Station $station): void
    {
        throw_if($station->users()->exists() || $station->memos()->exists() || $station->serviceContracts()->exists(), ValidationException::withMessages(['station' => 'This station is in use and cannot be deleted. Disable it instead.']));
        $station->delete();
    }

    public function deleteContractor(Contractor $contractor): void
    {
        throw_if($contractor->memos()->exists(), ValidationException::withMessages(['contractor' => 'This contractor is used by memos and cannot be deleted. Disable it instead.']));
        $contractor->delete();
    }
}
