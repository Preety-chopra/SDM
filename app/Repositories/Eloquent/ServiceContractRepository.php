<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Enums\SdmType;
use App\Models\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ServiceContractRepository implements ServiceContractRepositoryInterface
{
    public function paginate(): LengthAwarePaginator
    {
        return ServiceContract::query()
            ->with(['contractor:id,name', 'line:id,code,name', 'station:id,code,name'])
            ->latest('id')
            ->paginate(15);
    }

    public function create(array $attributes): ServiceContract
    {
        return ServiceContract::query()->create($attributes);
    }

    public function update(ServiceContract $contract, array $attributes): ServiceContract
    {
        $contract->update($attributes);

        return $contract->refresh();
    }

    public function hasPublishedOverlap(ServiceContract $contract): bool
    {
        return ServiceContract::query()
            ->whereKeyNot($contract->id)
            ->where('status', 'published')
            ->where('contractor_id', $contract->contractor_id)
            ->where('service_type', $contract->service_type->value)
            ->where(fn (Builder $query): Builder => $query
                ->where('station_id', $contract->station_id)
                ->orWhereNull('station_id'))
            ->whereDate('effective_from', '<=', $contract->effective_to)
            ->whereDate('effective_to', '>=', $contract->effective_from)
            ->exists();
    }

    public function activeFor(SdmType $type, ?int $contractorId = null, ?int $stationId = null): Collection
    {
        return ServiceContract::query()
            ->select(['id', 'contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to'])
            ->where('status', 'published')
            ->where('service_type', $type->value)
            ->when($contractorId, fn ($query) => $query->where('contractor_id', $contractorId))
            ->when($stationId, fn (Builder $query) => $query->where(fn (Builder $locationQuery): Builder => $locationQuery
                ->where('station_id', $stationId)
                ->orWhereNull('station_id')))
            ->orderBy('contract_number')
            ->get();
    }
}
