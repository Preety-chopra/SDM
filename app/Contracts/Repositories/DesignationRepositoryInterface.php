<?php

namespace App\Contracts\Repositories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Collection;

interface DesignationRepositoryInterface
{
    /** @return Collection<int, Designation> */
    public function all(): Collection;

    /** @return Collection<int, Designation> */
    public function active(): Collection;

    /** @param array{code: string, name: string, is_active: bool} $attributes */
    public function create(array $attributes): Designation;

    public function isUsed(Designation $designation): bool;

    public function delete(Designation $designation): void;
}
