<?php

namespace App\Services;

use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Contracts\Repositories\StationRepositoryInterface;
use App\Enums\SdmType;
use App\Models\ServiceContract;
use App\Models\Station;
use App\Models\User;
use App\Support\ContractStationMappingWorkbook;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContractStationMappingService
{
    public function __construct(
        private StationRepositoryInterface $stations,
        private ServiceContractRepositoryInterface $contracts,
        private AuditService $audit,
        private ContractStationMappingWorkbook $workbook,
    ) {}

    public function index(string $status, ?SdmType $serviceType, ?string $stationSearch): array
    {
        $stations = $this->stations->paginateForContractMapping($stationSearch);

        return [
            'stations' => $stations->appends([
                'status' => $status,
                'service_type' => $serviceType?->value,
                'station_search' => $stationSearch,
            ]),
            'stationGroups' => $stations->getCollection()->groupBy('line_id'),
            'contractsByStation' => $this->contractsByStation($stations->pluck('id')->all(), $status, $serviceType),
        ];
    }

    public function export(string $status, ?SdmType $serviceType, ?string $stationSearch, User $actor): string
    {
        $stations = $this->stations->allForContractMapping($stationSearch);
        $contractsByStation = $this->contractsByStation($stations->modelKeys(), $status, $serviceType);
        $rows = [];

        foreach ($stations as $station) {
            $agencies = $contractsByStation->get($station->id, collect())->groupBy('service_type');
            $row = [count($rows) + 1, $station->metroLine?->code ?? '', $station->code];

            foreach ([SdmType::Ticketing, SdmType::Cfs, SdmType::WandW, SdmType::Marshal] as $type) {
                $row[] = $agencies->get($type->value, collect())->pluck('contractor.name')->unique()->implode("\n");
            }

            $rows[] = $row;
        }

        $content = $this->workbook->write($rows);
        $this->audit->record($actor, $actor, 'contract.station_mapping_exported', after: [
            'status' => $status,
            'service_type' => $serviceType?->value,
            'station_search' => $stationSearch,
            'station_count' => count($rows),
        ]);

        return $content;
    }

    private function contractsByStation(array $stationIds, string $status, ?SdmType $serviceType): Collection
    {
        $contractsByStation = collect();

        foreach ($this->contracts->stationMappings($stationIds, $status, $serviceType) as $contract) {
            $assignedIds = $contract->stations->modelKeys() ?: [$contract->station_id];
            foreach ($assignedIds as $stationId) {
                $contractsByStation->getOrPut($stationId, fn () => collect())->push($contract);
            }
        }

        return $contractsByStation;
    }

    public function canReassign(Station $station, ServiceContract $source): bool
    {
        return $source->isPublished() && $station->is_active && $station->metroLine?->is_active
            && $this->assignedStations($source)->contains('id', $station->id);
    }

    public function replacements(ServiceContract $source): Collection
    {
        return $this->contracts->stationReplacements($source);
    }

    public function reassign(Station $station, ServiceContract $source, int $replacementId, User $actor): void
    {
        DB::transaction(function () use ($station, $source, $replacementId, $actor): void {
            $contracts = $this->contracts->lockServiceContracts($source->service_type);
            $source = $contracts->get($source->id);
            $replacement = $contracts->get($replacementId);

            if (! $source || ! $this->canReassign($station, $source)) {
                throw ValidationException::withMessages(['replacement_contract_id' => 'This station assignment has changed. Return to the list and select its current contract.']);
            }

            if (! $replacement || $source->is($replacement) || ! $replacement->isPublished() || ! $replacement->contractor?->is_active) {
                throw ValidationException::withMessages(['replacement_contract_id' => 'Select another published contract for the same service from an active contractor.']);
            }

            $replacementStations = $this->assignedStations($replacement);
            if ($replacementStations->isEmpty() && ! $replacement->is_station_unassigned) {
                throw ValidationException::withMessages(['replacement_contract_id' => 'Select a station-specific contract.']);
            }

            $this->contracts->setStations($source, $this->assignedStations($source)->where('id', '!=', $station->id), $actor->id);

            if (! $replacementStations->contains('id', $station->id)) {
                if ($this->contracts->hasPublishedOverlap($replacement, $station->id)) {
                    throw ValidationException::withMessages(['replacement_contract_id' => 'The replacement overlaps a published contract for this station, contractor, and service.']);
                }

                $this->contracts->setStations($replacement, $replacementStations->push($station), $actor->id);
            }

            $this->audit->record($station, $actor, 'contract.station_reassigned', $this->auditValues($source), $this->auditValues($replacement));
        }, attempts: 3);
    }

    private function assignedStations(ServiceContract $contract): Collection
    {
        $contract->loadMissing(['stations:id,line_id', 'station:id,line_id']);

        return $contract->stations->isNotEmpty() ? $contract->stations : collect([$contract->station])->filter();
    }

    private function auditValues(ServiceContract $contract): array
    {
        return [
            'contract_id' => $contract->id,
            'contractor_id' => $contract->contractor_id,
            'service_type' => $contract->service_type->value,
        ];
    }
}
