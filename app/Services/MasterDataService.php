<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MasterDataService
{
    public function __construct(
        private MasterDataRepositoryInterface $repository,
        private AuditService $audit,
    ) {}

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

    public function saveContractor(?Contractor $contractor, array $data, User $actor): Contractor
    {
        return DB::transaction(function () use ($contractor, $data, $actor): Contractor {
            $fields = ['name', 'code', 'contact_person', 'phone', 'address', 'is_active'];
            $before = $contractor?->only($fields) ?? [];
            $event = $contractor === null ? 'contractor.created' : 'contractor.updated';
            $contractor = $this->repository->saveContractor($contractor, $data);
            $this->audit->record($contractor, $actor, $event, $before, $contractor->only($fields));

            return $contractor;
        });
    }

    public function deleteLine(Line $line): void
    {
        throw_if($line->stations()->exists() || $line->serviceContracts()->exists(), ValidationException::withMessages(['line' => 'This line is assigned to one or more stations or contracts and cannot be deleted.']));
        $line->delete();
    }

    public function deleteStation(Station $station): void
    {
        throw_if($station->users()->exists() || $station->memos()->exists() || $station->serviceContracts()->exists() || $station->aftCashCheckEntries()->exists(), ValidationException::withMessages(['station' => 'This station is in use and cannot be deleted. Disable it instead.']));
        $station->delete();
    }

    public function deleteContractor(Contractor $contractor): void
    {
        throw_if($this->repository->contractorHasOutsourcedStaff($contractor), ValidationException::withMessages(['contractor' => 'This contractor has outsourced staff and cannot be deleted. Disable it instead.']));
        throw_if($contractor->memos()->exists(), ValidationException::withMessages(['contractor' => 'This contractor is used by memos and cannot be deleted. Disable it instead.']));
        $contractor->delete();
    }
}
