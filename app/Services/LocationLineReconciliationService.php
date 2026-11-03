<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class LocationLineReconciliationService
{
    public function __construct(
        private MasterDataRepositoryInterface $masterData,
        private ServiceContractRepositoryInterface $contracts,
        private LocationSeedService $locations,
        private AuditService $audit,
    ) {}

    /** @return array{mappings: array, unmapped: array, stations: int, contracts: int} */
    public function reconcile(bool $apply = false): array
    {
        $snapshot = json_decode(File::get(database_path('seeders/data/locations.json')), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($snapshot, $apply): array {
            $lines = $this->masterData->lines(lock: $apply)->keyBy('id');
            $stations = $this->masterData->stations(lock: $apply);
            $expected = collect($snapshot['stations'])->keyBy('code');
            $canonical = collect($snapshot['lines'])->keyBy('code');
            $targets = [];

            foreach ($stations as $station) {
                $row = $expected->get($station->code);
                if ($row === null) {
                    continue;
                }

                $source = $lines->get($station->line_id);
                if ($source === null) {
                    throw new RuntimeException("Station {$station->code} has no valid line. Reconcile its assignment before proceeding.");
                }

                $target = (string) $row['metro_line_code'];
                if (isset($targets[$source->id]) && $targets[$source->id] !== $target) {
                    throw new RuntimeException("Line {$source->code} contains stations from multiple snapshot lines ({$targets[$source->id]}, {$target}). No changes made; split or correct that line's station assignments first.");
                }

                $targets[$source->id] = $target;
            }

            $mappings = [];
            foreach ($targets as $sourceId => $targetCode) {
                $source = $lines->get($sourceId);
                if ((string) $source->code === $targetCode) {
                    continue;
                }

                $mappings[$sourceId] = [
                    'source_code' => $source->code,
                    'source_name' => $source->name,
                    'target_code' => $targetCode,
                    'target_name' => $canonical->get($targetCode)['name'],
                    'stations' => $stations->where('line_id', $sourceId)->count(),
                ];
            }

            $contracts = $mappings === [] ? collect() : $this->contracts->forLineReconciliation(array_keys($mappings), lock: $apply);
            $result = [
                'mappings' => array_values($mappings),
                'unmapped' => $lines->reject(fn ($line) => isset($targets[$line->id]) || $canonical->has($line->code))->pluck('code')->all(),
                'stations' => array_sum(array_column($mappings, 'stations')),
                'contracts' => $contracts->count(),
            ];

            if (! $apply || $mappings === []) {
                return $result;
            }

            $targetCodes = array_column($mappings, 'target_code');
            $resolved = $this->locations->seedLines($canonical->only($targetCodes)->values()->all());
            $lineIds = [];
            foreach ($mappings as $sourceId => $mapping) {
                $target = $resolved->get($mapping['target_code']);
                $lineIds[$sourceId] = $target->id;
            }

            // Names on canonical targets can belong to a different legacy code.
            foreach (array_unique($targetCodes) as $code) {
                $target = $resolved->get($code);
                $name = $canonical->get($code)['name'];
                if ($target->name !== $name) {
                    $before = $target->only(['name', 'color']);
                    $target = $this->masterData->saveLine($target, ['name' => $name]);
                    $this->audit->record($target, null, 'line.reconciled', $before, $target->only(['name', 'color']));
                }
            }

            foreach ($stations as $station) {
                if (! isset($lineIds[$station->line_id])) {
                    continue;
                }

                $before = $station->only('line_id');
                $station = $this->masterData->saveStation($station, ['line_id' => $lineIds[$station->line_id]]);
                $this->audit->record($station, null, 'station.line_reconciled', $before, $station->only('line_id'));
            }

            foreach ($contracts as $contract) {
                $before = ['line_id' => $contract->line_id, 'line_ids' => $contract->lines->modelKeys()];
                $after = [
                    'line_id' => $lineIds[$contract->line_id] ?? $contract->line_id,
                    'line_ids' => array_values(array_unique(array_map(fn ($id) => $lineIds[$id] ?? $id, $before['line_ids']))),
                ];

                $contract = $this->contracts->update($contract, ['line_id' => $after['line_id']]);
                $this->contracts->syncLines($contract, $after['line_ids']);
                $this->audit->record($contract, null, 'contract.lines_reconciled', $before, $after);
            }

            return $result;
        });
    }
}
