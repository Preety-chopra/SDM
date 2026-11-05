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
        return Designation::query()
            ->active()
            ->orderByRaw('CASE id
                WHEN 5 THEN 1
                WHEN 4 THEN 2
                WHEN 3 THEN 3
                WHEN 2 THEN 4
                WHEN 1 THEN 5
                WHEN 13 THEN 6
                WHEN 12 THEN 7
                WHEN 11 THEN 8
                WHEN 10 THEN 9
                WHEN 9 THEN 10
                WHEN 8 THEN 11
                WHEN 7 THEN 12
                WHEN 6 THEN 13
                ELSE 14 END')
            ->orderBy('name')
            ->orderBy('code')
            ->get();
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
