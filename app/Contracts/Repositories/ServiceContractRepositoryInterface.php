<?php

namespace App\Contracts\Repositories;

use App\Enums\SdmType;
use App\Models\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceContractRepositoryInterface
{
    public function paginate(string $status, ?SdmType $serviceType = null): LengthAwarePaginator;

    public function create(array $attributes): ServiceContract;

    public function findByReference(int $contractorId, SdmType $serviceType, string $contractNumber): ?ServiceContract;

    public function update(ServiceContract $contract, array $attributes): ServiceContract;

    public function forLineReconciliation(array $lineIds, bool $lock = false): Collection;

    public function syncLines(ServiceContract $contract, array $lineIds): void;

    public function hasPublishedOverlap(ServiceContract $contract, ?int $stationId = null): bool;

    public function stationMappings(array $stationIds, string $status, ?SdmType $type): Collection;

    public function stationReplacements(ServiceContract $contract): Collection;

    public function lockServiceContracts(SdmType $type): Collection;

    public function setStations(ServiceContract $contract, Collection $stations, int $actorId): void;

    public function contractorServiceTypes(): Collection;

    public function activeFor(?SdmType $type, ?int $contractorId = null, ?int $stationId = null): Collection;
}
