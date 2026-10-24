<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\DesignationRepositoryInterface;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class DesignationRepository implements DesignationRepositoryInterface
{
    public function all(): Collection
    {
        return Designation::query()->orderBy('name')->orderBy('code')->get();
    }

    public function active(): Collection
    {
        return Designation::query()->active()->orderBy('name')->orderBy('code')->get();
    }

    public function create(array $attributes): Designation
    {
        return Designation::query()->create($attributes);
    }

    public function isUsed(Designation $designation): bool
    {
        return User::query()->where('designation', $designation->code)->exists();
    }

    public function delete(Designation $designation): void
    {
        $designation->delete();
    }
}
