<?php

namespace App\Contracts\Repositories;

use App\Enums\SdmType;
use App\Models\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceContractRepositoryInterface
{
    public function paginate(): LengthAwarePaginator;

    public function create(array $attributes): ServiceContract;

    public function update(ServiceContract $contract, array $attributes): ServiceContract;

    public function hasPublishedOverlap(ServiceContract $contract): bool;

    public function activeFor(SdmType $type, ?int $contractorId = null, ?int $stationId = null): Collection;
}
