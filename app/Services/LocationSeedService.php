<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LocationSeedService
{
    public function __construct(
        private MasterDataRepositoryInterface $masterData,
        private AuditService $audit,
    ) {}

    public function seedLines(array $rows): Collection
    {
        return DB::transaction(function () use ($rows): Collection {
            $lines = $this->masterData->lines()->keyBy('code');
            foreach ($rows as $attributes) {
                if ($lines->has($attributes['code'])) {
                    continue;
                }

                $line = $this->masterData->saveLine(null, $attributes);
                $lines->put($line->code, $line);
                $this->audit->record($line, null, 'line.created', after: $attributes);
            }

            return $lines;
        });
    }

    public function seedStations(array $snapshot): Collection
    {
        return DB::transaction(function () use ($snapshot): Collection {
            $lines = $this->seedLines($snapshot['lines']);
            $stations = $this->masterData->stations()->keyBy('code');
            foreach ($snapshot['stations'] as $row) {
                $lineId = $lines->get($row['metro_line_code'])->id;
                if ($stations->has($row['code'])) {
                    if ($stations->get($row['code'])->line_id !== $lineId) {
                        throw ValidationException::withMessages([
                            'stations' => "Station {$row['code']} belongs to a different line. Reconcile its mapping before seeding.",
                        ]);
                    }

                    continue;
                }

                $attributes = [...Arr::except($row, ['metro_line_code']), 'line_id' => $lineId];
                $station = $this->masterData->saveStation(null, $attributes);
                $stations->put($station->code, $station);
                $this->audit->record($station, null, 'station.created', after: $attributes);
            }

            return $stations;
        });
    }
}
