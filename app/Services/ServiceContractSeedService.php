<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Enums\SdmType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ServiceContractSeedService
{
    public function __construct(
        private MasterDataRepositoryInterface $masterData,
        private ServiceContractRepositoryInterface $contracts,
        private AuditService $audit,
        private LocationSeedService $locations,
    ) {}

    public function seed(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $stations = $this->locations->seedStations($snapshot);
            $lines = $this->masterData->lines()->keyBy('code');
            $contractors = $this->masterData->contractors()->keyBy('name');
            foreach ($snapshot['contractors'] as $attributes) {
                if ($contractors->has($attributes['name'])) {
                    continue;
                }

                $contractor = $this->masterData->saveContractor(null, $attributes);
                $contractors->put($contractor->name, $contractor);
                $this->audit->record($contractor, null, 'contractor.created', after: $attributes);
            }

            foreach ($snapshot['contracts'] as $row) {
                $contractor = $contractors->get($row['contractor_name']);
                // Any existing version takes precedence over the seed snapshot.
                if ($this->contracts->findByReference($contractor->id, SdmType::from($row['service_type']), $row['contract_number'])) {
                    continue;
                }

                $lineIds = array_map(fn (string $code): int => $lines->get($code)->id, $row['line_codes']);
                $stationIds = array_map(fn (string $code): int => $stations->get($code)->id, $row['station_codes']);
                $attributes = [
                    ...Arr::only($row, ['contract_group', 'version', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to', 'status', 'published_at']),
                    'contractor_id' => $contractor->id,
                    'line_id' => $lines->get($row['primary_line_code'])?->id,
                    'station_id' => $stations->get($row['primary_station_code'])?->id,
                    'cmc_staff_id' => null,
                    'created_by' => null,
                    'updated_by' => null,
                ];
                $contract = $this->contracts->create($attributes);
                $contract->lines()->sync($lineIds);
                $contract->stations()->sync($stationIds);
                $contract->penaltyClauses()->createMany($row['clauses']);
                $this->audit->record($contract, null, 'contract.seeded', after: [
                    ...$attributes,
                    'line_ids' => $lineIds,
                    'station_ids' => $stationIds,
                    'penalty_clause_count' => count($row['clauses']),
                    'snapshot_date' => $snapshot['snapshot_date'],
                ]);
            }
        });
    }
}
