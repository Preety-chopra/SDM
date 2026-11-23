<?php

namespace App\Services;

use App\Contracts\Repositories\LineTopologyRepositoryInterface;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LineTopologyService
{
    public function __construct(
        private LineTopologyRepositoryInterface $topology,
        private ServiceContractService $contracts,
        private AuditService $audit,
    ) {}

    public function editor(Line $line): array
    {
        return ['line' => $line, 'stations' => $this->topology->stations([$line->id]), 'sections' => $this->topology->sections($line)];
    }

    public function filterOptions(Collection $lines): Collection
    {
        return $lines->concat($this->topology->sectionCodes())->unique('code')->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function replaceSections(Line $line, array $sections, User $actor): void
    {
        DB::transaction(function () use ($line, $sections, $actor): void {
            $lines = $this->topology->lockLines();
            $line = $lines->get($line->id);
            $this->requireActive($line);
            $stations = $this->topology->stations([$line->id], lock: true);
            $before = $this->topology->sections($line)->whereNull('retired_at')->map->only(['code', 'name', 'station_ids'])->values()->all();
            $resolved = [];
            $assigned = [];
            $reserved = $lines->pluck('code')->map(fn (string $code): string => mb_strtolower($code))
                ->merge($this->topology->sectionCodes()->where('line_id', '!=', $line->id)->pluck('code')->map(fn (string $code): string => mb_strtolower($code)));
            foreach ($sections as $index => $section) {
                if ($reserved->contains(mb_strtolower($section['code']))) {
                    throw ValidationException::withMessages(["sections.$index.code" => 'This code is already used by another line or section.']);
                }
                $ids = $this->resolveStations($section, $stations, $index);
                if ($ids === [] || array_intersect($assigned, $ids) !== []) {
                    throw ValidationException::withMessages(['sections' => 'Every section needs stations, and a station can belong to only one section.']);
                }
                $assigned = [...$assigned, ...$ids];
                $resolved[] = ['code' => $section['code'], 'name' => $section['name'], 'station_ids' => $ids];
            }
            if ($sections !== [] && count($assigned) !== $stations->count()) {
                throw ValidationException::withMessages(['sections' => 'Assign every station on this line to exactly one section, including inactive stations.']);
            }
            $this->topology->retireSections([$line->id]);
            foreach ($resolved as $section) {
                $created = $this->topology->createSection($line, $section);
                $this->topology->assignSection($section['station_ids'], $created->id);
            }
            $this->audit->record($line, $actor, 'line.sections_changed', ['sections' => $before], ['sections' => $resolved]);
        }, attempts: 3);
    }

    public function merge(array $ids, int $targetId, User $actor): void
    {
        DB::transaction(function () use ($ids, $targetId, $actor): void {
            $all = $this->topology->lockLines();
            $lines = $all->only($ids)->keyBy('id');
            if ($lines->count() !== count($ids) || ! $lines->has($targetId)) {
                throw ValidationException::withMessages(['line_ids' => 'Select existing lines and choose one of them as the resulting line.']);
            }
            foreach ($lines as $line) {
                $this->requireActive($line);
            }
            $stations = $this->topology->stations($ids, lock: true);
            $target = $lines->get($targetId);
            foreach ($this->topology->affectedContracts($ids) as $contract) {
                if ($contract->location_snapshot === null) {
                    $labels = $this->contracts->locationSummaries($contract)->all();
                    $this->topology->snapshotContract($contract, $labels);
                    $this->audit->record($contract, $actor, 'contract.location_preserved', after: ['location_snapshot' => $labels]);
                }
            }
            $before = ['lines' => $lines->map->only(['id', 'code', 'name'])->values()->all(), 'stations' => $stations->map->only(['id', 'line_id', 'line_section_id'])->all()];
            $this->topology->retireSections($ids);
            $this->topology->merge($target, $lines);
            $this->audit->record($target, $actor, 'line.merged', $before, ['target_line_id' => $targetId, 'station_ids' => $stations->modelKeys()]);
        }, attempts: 3);
    }

    private function requireActive(Line $line): void
    {
        if (! $line->is_active || $line->merged_into_line_id !== null) {
            throw ValidationException::withMessages(['line' => 'Choose an active line that has not been merged.']);
        }
    }

    private function resolveStations(array $section, Collection $stations, int $index): array
    {
        if ($section['selection_mode'] === 'range') {
            $from = $stations->firstWhere('id', $section['from_station_id']);
            $to = $stations->firstWhere('id', $section['to_station_id']);
            if ($from?->sequence_no === null || $to?->sequence_no === null) {
                throw ValidationException::withMessages(["sections.$index.from_station_id" => 'Choose two sequenced stations belonging to this line.']);
            }

            return $stations->filter(fn (Station $station): bool => $station->sequence_no !== null
                && $station->sequence_no >= min($from->sequence_no, $to->sequence_no)
                && $station->sequence_no <= max($from->sequence_no, $to->sequence_no))->pluck('id')->all();
        }
        $ids = array_map('intval', $section['station_ids']);
        if (array_diff($ids, $stations->pluck('id')->all()) !== []) {
            throw ValidationException::withMessages(["sections.$index.station_ids" => 'Select only stations belonging to this line.']);
        }

        return $stations->whereIn('id', $ids)->pluck('id')->all();
    }
}
