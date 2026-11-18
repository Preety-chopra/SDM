<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\HistoricalSdmRepositoryInterface;
use App\Models\Contractor;
use App\Models\HistoricalSdm;
use App\Models\HistoricalSdmImport;
use App\Models\OutsourcedStaff;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

final class HistoricalSdmRepository implements HistoricalSdmRepositoryInterface
{
    public function findImport(int $contractorId, string $sha256, string $sheet): ?HistoricalSdmImport
    {
        return HistoricalSdmImport::query()->where('contractor_id', $contractorId)->where('source_sha256', $sha256)->where('source_sheet', $sheet)->first();
    }

    public function lockContractor(int $id): void
    {
        Contractor::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function staff(int $contractorId): Collection
    {
        return OutsourcedStaff::query()->where('contractor_id', $contractorId)->get(['id', 'employee_id', 'aadhaar_lookup_hash']);
    }

    public function stations(): Collection
    {
        return Station::query()->with('metroLine:id,code')->get(['id', 'code', 'station_code', 'line_code', 'line_id']);
    }

    public function createImport(array $attributes): HistoricalSdmImport
    {
        return HistoricalSdmImport::query()->create($attributes);
    }

    public function insertRecords(array $records): void
    {
        // Hydrate casts before bulk insertion so Aadhaar is never persisted in plaintext.
        $timestamp = now()->toDateTimeString();
        foreach (array_chunk($records, 250) as $chunk) {
            HistoricalSdm::query()->insert(array_map(fn (array $record): array => [
                ...(new HistoricalSdm($record))->getAttributes(), 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], $chunk));
        }
    }
}
