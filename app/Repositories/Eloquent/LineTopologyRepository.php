<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LineTopologyRepositoryInterface;
use App\Models\Line;
use App\Models\LineSection;
use App\Models\ServiceContract;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

final class LineTopologyRepository implements LineTopologyRepositoryInterface
{
    public function lockLines(): Collection
    {
        // Shared lock order serializes section changes and merges, including code checks.
        return Line::query()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function stations(array $lineIds, bool $lock = false): Collection
    {
        return Station::query()->whereIn('line_id', $lineIds)->orderByRaw('sequence_no IS NULL')
            ->orderBy('sequence_no')->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
    }

    public function sections(Line $line): Collection
    {
        return $line->sections()->orderByDesc('id')->get();
    }

    public function sectionCodes(): Collection
    {
        return LineSection::query()->orderBy('code')->orderByDesc('id')->get(['id', 'line_id', 'code', 'name'])->unique('code')->values();
    }

    public function retireSections(array $lineIds): void
    {
        Station::query()->whereIn('line_id', $lineIds)->update(['line_section_id' => null]);
        LineSection::query()->whereIn('line_id', $lineIds)->whereNull('retired_at')->update(['retired_at' => now()]);
    }

    public function createSection(Line $line, array $data): LineSection
    {
        return $line->sections()->create([...$data, 'effective_from' => now()]);
    }

    public function assignSection(array $stationIds, int $sectionId): void
    {
        Station::query()->whereKey($stationIds)->update(['line_section_id' => $sectionId]);
    }

    public function merge(Line $target, Collection $lines): void
    {
        $sourceIds = $lines->except($target->id)->modelKeys();
        Station::query()->whereIn('line_id', $sourceIds)->update(['line_id' => $target->id]);
        Line::query()->whereKey($sourceIds)->update(['is_active' => false, 'merged_into_line_id' => $target->id]);
    }

    public function affectedContracts(array $lineIds): Collection
    {
        return ServiceContract::query()->where(fn ($query) => $query->whereIn('line_id', $lineIds)
            ->orWhereHas('lines', fn ($query) => $query->whereIn('lines.id', $lineIds))
            ->orWhereHas('stations', fn ($query) => $query->whereIn('stations.line_id', $lineIds)))
            ->with(['lines', 'stations'])->orderBy('id')->lockForUpdate()->get();
    }

    public function snapshotContract(ServiceContract $contract, array $labels): void
    {
        $contract->location_snapshot = $labels;
        $contract->timestamps = false;
        $contract->save();
        $contract->timestamps = true;
    }
}
