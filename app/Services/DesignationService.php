<?php

namespace App\Services;

use App\Contracts\Repositories\DesignationRepositoryInterface;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DesignationService
{
    public function __construct(
        private DesignationRepositoryInterface $designations,
        private AuditService $audit,
    ) {}

    /** @return Collection<int, Designation> */
    public function all(): Collection
    {
        return $this->designations->all();
    }

    /** @return Collection<int, Designation> */
    public function active(): Collection
    {
        return $this->designations->active();
    }

    /** @param array{code: string, name: string, is_active: bool} $data */
    public function create(array $data, User $actor): Designation
    {
        return DB::transaction(function () use ($data, $actor): Designation {
            $designation = $this->designations->create($data);
            $this->audit->record($designation, $actor, 'designation.created', after: $data);

            return $designation;
        });
    }

    public function delete(Designation $designation, User $actor): void
    {
        DB::transaction(function () use ($designation, $actor): void {
            if ($this->designations->isUsed($designation)) {
                throw ValidationException::withMessages([
                    'designation' => 'Remove this designation from all users before deleting it.',
                ]);
            }

            $this->audit->record($designation, $actor, 'designation.deleted', before: [
                'code' => $designation->code,
                'name' => $designation->name,
                'is_active' => $designation->is_active,
            ]);
            $this->designations->delete($designation);
        });
    }
}
