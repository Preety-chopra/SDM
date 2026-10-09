<?php

namespace App\Services;

use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Models\ServiceContract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ServiceContractService
{
    public function __construct(
        private ServiceContractRepositoryInterface $contracts,
        private AuditService $audit,
    ) {}

    public function paginate(): LengthAwarePaginator
    {
        return $this->contracts->paginate();
    }

    public function create(User $actor, array $data): ServiceContract
    {
        $path = null;

        try {
            return DB::transaction(function () use ($actor, $data, &$path): ServiceContract {
                $document = $data['source_document'] ?? null;
                $attributes = Arr::except($data, ['source_document', 'clauses', 'line_ids', 'station_ids']);
                $attributes['line_id'] = $data['line_ids'][0];
                $attributes['station_id'] = $data['station_ids'][0];
                $attributes += [
                    'contract_group' => Str::ulid()->toString(),
                    'version' => 1,
                    'status' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ];

                if ($document instanceof UploadedFile) {
                    $path = $document->store('contracts', 'local');
                    $attributes += $this->documentAttributes($document, $path);
                }

                $contract = $this->contracts->create($attributes);
                $this->syncLocations($contract, $data);
                $contract->seedPenaltyClauses();
                $this->syncClauses($contract, $data['clauses'] ?? []);
                $this->audit->record($contract, $actor, 'contract.created', after: $this->auditValues($contract));

                return $contract->load(['contractor', 'penaltyClauses']);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function update(ServiceContract $contract, User $actor, array $data): ServiceContract
    {
        throw_if($contract->status !== 'draft' || $contract->memos()->exists(), ValidationException::withMessages([
            'contract' => 'Published or referenced contract versions cannot be edited. Create a revision instead.',
        ]));

        $oldPath = $contract->source_document_path;
        $newPath = null;

        try {
            $updated = DB::transaction(function () use ($contract, $actor, $data, &$newPath): ServiceContract {
                $before = $this->auditValues($contract);
                $document = $data['source_document'] ?? null;
                $attributes = Arr::except($data, ['source_document', 'clauses', 'line_ids', 'station_ids']);
                $attributes['line_id'] = $data['line_ids'][0];
                $attributes['station_id'] = $data['station_ids'][0];
                $attributes['updated_by'] = $actor->id;

                if ($document instanceof UploadedFile) {
                    $newPath = $document->store('contracts', 'local');
                    $attributes += $this->documentAttributes($document, $newPath);
                }

                $contract = $this->contracts->update($contract, $attributes);
                $this->syncLocations($contract, $data);
                $this->syncClauses($contract, $data['clauses'] ?? []);
                $this->audit->record($contract, $actor, 'contract.updated', $before, $this->auditValues($contract));

                return $contract->load(['contractor', 'penaltyClauses']);
            });

            if ($newPath && $oldPath && ! ServiceContract::query()->whereKeyNot($updated->id)->where('source_document_path', $oldPath)->exists()) {
                Storage::disk('local')->delete($oldPath);
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
    }

    public function publish(ServiceContract $contract, User $actor): ServiceContract
    {
        if ($contract->status !== 'draft') {
            throw ValidationException::withMessages(['contract' => 'Only draft contract versions can be published.']);
        }
        $this->ensureLocationPivots($contract);

        if (! $contract->lines()->exists() || ! $contract->stations()->exists()) {
            throw ValidationException::withMessages(['station_ids' => 'Assign at least one active line and station before publishing this contract.']);
        }
        if ($this->contracts->hasPublishedOverlap($contract)) {
            throw ValidationException::withMessages(['effective_from' => 'This contract overlaps another published contract for the same station, contractor, and service.']);
        }

        return DB::transaction(function () use ($contract, $actor): ServiceContract {
            $before = $this->auditValues($contract);
            $contract = $this->contracts->update($contract, ['status' => 'published', 'published_at' => now(), 'updated_by' => $actor->id]);
            $this->audit->record($contract, $actor, 'contract.published', $before, $this->auditValues($contract));

            return $contract;
        });
    }

    public function revise(ServiceContract $contract, User $actor): ServiceContract
    {
        throw_if($contract->status === 'draft', ValidationException::withMessages(['contract' => 'Edit the existing draft instead of creating another revision.']));

        return DB::transaction(function () use ($contract, $actor): ServiceContract {
            $contract->load('penaltyClauses');
            $this->ensureLocationPivots($contract);
            $revision = $this->contracts->create([
                ...Arr::only($contract->getAttributes(), ['contract_group', 'contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to', 'cmc_staff_id', 'source_document_name', 'source_document_path', 'source_document_mime', 'source_document_size']),
                'version' => ServiceContract::query()->where('contract_group', $contract->contract_group)->max('version') + 1,
                'status' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $revision->lines()->sync($contract->lines()->pluck('lines.id'));
            $revision->stations()->sync($contract->stations()->pluck('stations.id'));
            $revision->penaltyClauses()->createMany($contract->penaltyClauses->map(fn ($clause) => Arr::except($clause->getAttributes(), ['id', 'service_contract_id', 'created_at', 'updated_at']))->all());
            $this->audit->record($revision, $actor, 'contract.revision_created', after: ['source_contract_id' => $contract->id, 'version' => $revision->version]);

            return $revision;
        });
    }

    public function retire(ServiceContract $contract, User $actor): ServiceContract
    {
        throw_if($contract->status !== 'published', ValidationException::withMessages(['contract' => 'Only a published contract can be retired.']));

        return DB::transaction(function () use ($contract, $actor): ServiceContract {
            $contract = $this->contracts->update($contract, ['status' => 'retired', 'updated_by' => $actor->id]);
            $this->audit->record($contract, $actor, 'contract.retired', after: ['status' => 'retired']);

            return $contract;
        });
    }

    public function delete(ServiceContract $contract, User $actor): void
    {
        throw_if($contract->status !== 'draft' || $contract->memos()->exists(), ValidationException::withMessages(['contract' => 'Only an unused draft contract can be deleted.']));
        $path = $contract->source_document_path;
        DB::transaction(function () use ($contract, $actor): void {
            $this->audit->record($contract, $actor, 'contract.deleted', before: $this->auditValues($contract));
            $contract->delete();
        });
        if ($path && ! ServiceContract::query()->where('source_document_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }
    }

    private function syncClauses(ServiceContract $contract, array $clauses): void
    {
        if ($clauses === []) {
            return;
        }

        $contract->penaltyClauses()->delete();
        $contract->penaltyClauses()->createMany(array_values($clauses));
    }

    private function syncLocations(ServiceContract $contract, array $data): void
    {
        $contract->lines()->sync($data['line_ids']);
        $contract->stations()->sync($data['station_ids']);
    }

    private function ensureLocationPivots(ServiceContract $contract): void
    {
        if (! $contract->lines()->exists() && $contract->line_id !== null) {
            $contract->lines()->sync([$contract->line_id]);
        }

        if (! $contract->stations()->exists() && $contract->station_id !== null) {
            $contract->stations()->sync([$contract->station_id]);
        }
    }

    private function documentAttributes(UploadedFile $document, string $path): array
    {
        return [
            'source_document_name' => $document->getClientOriginalName(),
            'source_document_path' => $path,
            'source_document_mime' => $document->getMimeType(),
            'source_document_size' => $document->getSize(),
        ];
    }

    private function auditValues(ServiceContract $contract): array
    {
        return [
            ...Arr::only($contract->getAttributes(), ['contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to', 'cmc_staff_id', 'status', 'version']),
            'line_ids' => $contract->lines()->pluck('lines.id')->all(),
            'station_ids' => $contract->stations()->pluck('stations.id')->all(),
        ];
    }
}
