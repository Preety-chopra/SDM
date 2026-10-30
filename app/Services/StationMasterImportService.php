<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\StationRepositoryInterface;
use App\Models\Station;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StationMasterImportService
{
    public function __construct(
        private StationRepositoryInterface $stations,
        private MasterDataRepositoryInterface $masterData,
        private AuditService $audit,
    ) {}

    /** @return array{created: int, updated: int, unchanged: int} */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = $this->stations->masterRows();
            $lines = $this->masterData->lines()->keyBy('code');
            $stations = $this->stations->lockForMasterImport();
            $codeCounts = $rows->countBy(fn (object $row): string => mb_strtoupper(trim($row->station_code ?? '')));
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($rows as $row) {
                $line = $lines->get($row->line_code);

                if (! $line) {
                    throw new RuntimeException("No line matches station_master line_code [{$row->line_code}] for station_id [{$row->station_id}].");
                }

                $code = mb_strtoupper(trim($row->station_code ?? ''));
                $station = $stations->first(fn (Station $station): bool => $station->station_id === (int) $row->station_id)
                    ?? $stations->first(fn (Station $station): bool => $code !== ''
                        && $station->station_id === null
                        && mb_strtoupper($station->code) === $code
                        && ($station->line_id === $line->id || $codeCounts->get($code) === 1));
                $created = $station === null;
                $station ??= new Station;
                $attributes = [
                    'station_id' => $row->station_id,
                    'line_code' => $row->line_code,
                    'station_name' => $row->station_name,
                    'station_code' => $row->station_code,
                    'sequence_no' => $row->sequence_no,
                    'name' => $row->station_name ?? $station->name ?? 'Station '.$row->station_id,
                    'line_id' => $line->id,
                ];

                if ($created) {
                    $attributes['code'] = $this->availableCode($code, (int) $row->station_id, $stations);
                    $attributes['is_active'] = true;
                }

                $before = $created ? [] : $station->only(array_keys($attributes));
                $station->fill($attributes);

                if (! $created && ! $station->isDirty()) {
                    $counts['unchanged']++;

                    continue;
                }

                $this->stations->saveImported($station);
                $this->audit->record($station, null, 'station.master_imported', $before, $station->only(array_keys($attributes)));
                $counts[$created ? 'created' : 'updated']++;

                if ($created) {
                    $stations->push($station);
                }
            }

            return $counts;
        });
    }

    private function availableCode(string $referenceCode, int $masterId, Collection $stations): string
    {
        $base = $referenceCode !== '' ? $referenceCode : 'MASTER-'.$masterId;
        $code = $base;
        $suffix = 0;

        while ($stations->contains(fn (Station $station): bool => mb_strtoupper($station->code) === $code)) {
            $code = mb_substr($base, 0, 15).'-'.$masterId.($suffix === 0 ? '' : '-'.$suffix);
            $suffix++;
        }

        return $code;
    }
}
