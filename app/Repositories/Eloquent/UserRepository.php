<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class UserRepository implements UserRepositoryInterface
{
    public function paginateByRole(UserRole $role, ?string $search, ?bool $enabled): LengthAwarePaginator
    {
        return User::query()
            ->select(['id', 'name', 'email', 'employee_number', 'phone', 'designation', 'station_id', 'is_enabled', 'created_at'])
            ->with('station:id,code,name')
            ->role($role->value)
            ->when($search, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($enabled !== null, fn (Builder $query) => $query->where('is_enabled', $enabled))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function managedRoleCounts(): array
    {
        $counts = [];

        foreach (UserRole::managed() as $role) {
            $query = User::query()->role($role->value);
            $total = (clone $query)->count();
            $enabled = (clone $query)->where('is_enabled', true)->count();

            $counts[$role->value] = [
                'total' => $total,
                'enabled' => $enabled,
                'disabled' => $total - $enabled,
            ];
        }

        return $counts;
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    public function setEnabled(User $user, bool $enabled): User
    {
        $user->update(['is_enabled' => $enabled]);

        return $user->refresh();
    }
}
