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

    public function hasPublishedOverlap(ServiceContract $contract): bool
    {
        $stationIds = $contract->stations()->pluck('stations.id');

        if ($stationIds->isEmpty() && $contract->station_id !== null) {
            $stationIds->push($contract->station_id);
        }

        return ServiceContract::query()
            ->whereKeyNot($contract->id)
            ->where('status', 'published')
            ->where('contractor_id', $contract->contractor_id)
            ->where('service_type', $contract->service_type->value)
            ->where(fn (Builder $query): Builder => $query
                ->whereHas('stations', fn (Builder $stationQuery): Builder => $stationQuery->whereIn('stations.id', $stationIds))
                ->orWhere(fn (Builder $legacyQuery): Builder => $legacyQuery
                    ->doesntHave('stations')
                    ->where(fn (Builder $fallbackQuery): Builder => $fallbackQuery
                        ->whereIn('station_id', $stationIds)
                        ->orWhereNull('station_id'))))
            ->whereDate('effective_from', '<=', $contract->effective_to)
            ->whereDate('effective_to', '>=', $contract->effective_from)
            ->exists();
    }

    public function activeFor(?SdmType $type, ?int $contractorId = null, ?int $stationId = null): Collection
    {
        return ServiceContract::query()
            ->select(['id', 'contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to'])
            ->where('status', 'published')
            ->when($type, fn (Builder $query): Builder => $query->where('service_type', $type->value))
            ->when($contractorId, fn ($query) => $query->where('contractor_id', $contractorId))
            ->when($stationId, fn (Builder $query) => $query->where(fn (Builder $locationQuery): Builder => $locationQuery
                ->whereHas('stations', fn (Builder $stationQuery): Builder => $stationQuery->whereKey($stationId))
                ->orWhere(fn (Builder $legacyQuery): Builder => $legacyQuery
                    ->doesntHave('stations')
                    ->where(fn (Builder $fallbackQuery): Builder => $fallbackQuery
                        ->where('station_id', $stationId)
                        ->orWhereNull('station_id')))))
            ->orderBy('contract_number')
            ->get();
    }
}
