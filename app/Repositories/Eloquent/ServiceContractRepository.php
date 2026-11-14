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
    public function paginate(string $status, ?SdmType $serviceType = null): LengthAwarePaginator
    {
        return ServiceContract::query()
            ->where('status', $status)
            ->when($serviceType, fn (Builder $query): Builder => $query->where('service_type', $serviceType->value))
            ->with(['contractor:id,name', 'line:id,code,name', 'station:id,code,name'])
            ->latest('id')
            ->paginate(15);
    }

    public function create(array $attributes): ServiceContract
    {
        return ServiceContract::query()->create($attributes);
    }

    public function findByReference(int $contractorId, SdmType $serviceType, string $contractNumber): ?ServiceContract
    {
        return ServiceContract::query()
            ->where('contractor_id', $contractorId)
            ->where('service_type', $serviceType->value)
            ->where('contract_number', $contractNumber)
            ->orderByDesc('version')
            ->first();
    }

    public function update(ServiceContract $contract, array $attributes): ServiceContract
    {
        $contract->update($attributes);

        return $contract->refresh();
    }

    public function forLineReconciliation(array $lineIds, bool $lock = false): Collection
    {
        return ServiceContract::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('line_id', $lineIds)
                ->orWhereHas('lines', fn (Builder $lines) => $lines->whereIn('lines.id', $lineIds)))
            ->with('lines:id')
            ->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get();
    }

    public function syncLines(ServiceContract $contract, array $lineIds): void
    {
        $contract->lines()->sync($lineIds);
    }

    public function hasPublishedOverlap(ServiceContract $contract, ?int $stationId = null): bool
    {
        $stationIds = $stationId === null ? $contract->stations()->pluck('stations.id')->all() : [$stationId];
        if ($stationIds === [] && $contract->station_id !== null) {
            $stationIds = [$contract->station_id];
        }

        return ServiceContract::query()
            ->whereKeyNot($contract->id)
            ->where('status', 'published')
            ->where('contractor_id', $contract->contractor_id)
            ->where('service_type', $contract->service_type->value)
            ->coversStations($stationIds)
            ->whereDate('effective_from', '<=', $contract->effective_to)
            ->whereDate('effective_to', '>=', $contract->effective_from)
            ->exists();
    }

    public function stationMappings(array $stationIds, string $status, ?SdmType $type): Collection
    {
        return ServiceContract::query()
            ->where('status', $status)
            ->coversStations($stationIds, includeUnrestricted: false)
            ->when($type, fn (Builder $query): Builder => $query->where('service_type', $type->value))
            ->with(['contractor:id,name', 'stations' => fn ($query) => $query->whereKey($stationIds)->select('stations.id')])
            ->orderBy('service_type')->orderByDesc('effective_from')->orderBy('id')
            ->get();
    }

    public function stationReplacements(ServiceContract $contract): Collection
    {
        return ServiceContract::query()
            ->whereKeyNot($contract->id)
            ->where('service_type', $contract->service_type->value)
            ->where('status', 'published')
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('station_id')->orHas('stations')->orWhere('is_station_unassigned', true))
            ->whereHas('contractor', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('contractor:id,name')
            ->get()
            ->sortBy([['contractor.name', 'asc'], ['effective_from', 'desc'], ['id', 'asc']]);
    }

    public function lockServiceContracts(SdmType $type): Collection
    {
        return ServiceContract::query()
            ->where('service_type', $type->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()->keyBy('id');
    }

    public function setStations(ServiceContract $contract, Collection $stations, int $actorId): void
    {
        $primary = $stations->firstWhere('id', $contract->station_id) ?? $stations->first();
        $contract->stations()->sync($stations->pluck('id')->all());
        $this->syncLines($contract, $stations->pluck('line_id')->filter()->unique()->all());
        $this->update($contract, [
            'station_id' => $primary?->id,
            'line_id' => $primary?->line_id,
            'is_station_unassigned' => $stations->isEmpty(),
            'updated_by' => $actorId,
        ]);
    }

    public function contractorServiceTypes(): Collection
    {
        return ServiceContract::query()
            ->whereHas('contractor', fn (Builder $query): Builder => $query->where('is_active', true))
            ->toBase()
            ->select(['service_type', 'contractor_id'])
            ->distinct()
            ->get();
    }

    public function activeFor(?SdmType $type, ?int $contractorId = null, ?int $stationId = null): Collection
    {
        return ServiceContract::query()
            ->select(['id', 'contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to'])
            ->where('status', 'published')
            ->when($type, fn (Builder $query): Builder => $query->where('service_type', $type->value))
            ->when($contractorId, fn ($query) => $query->where('contractor_id', $contractorId))
            ->when($stationId,
                fn (Builder $query): Builder => $query->coversStations([$stationId]),
                fn (Builder $query): Builder => $query->where('is_station_unassigned', false),
            )
            ->orderBy('contract_number')
            ->get();
    }
}
