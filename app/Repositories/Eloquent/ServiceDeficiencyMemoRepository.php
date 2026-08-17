<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ServiceDeficiencyMemoRepository implements ServiceDeficiencyMemoRepositoryInterface
{
    public function paginateFor(User $user): LengthAwarePaginator
    {
        return ServiceDeficiencyMemo::query()
            ->select(['id', 'service_type', 'serial_number', 'station_name', 'line_name', 'incident_date', 'status', 'created_by', 'created_at'])
            ->when(! $user->can(UserPermission::ViewAllMemos->value), fn ($query) => $query->whereBelongsTo($user, 'creator'))
            ->latest()
            ->paginate(15);
    }

    public function create(array $attributes): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create($attributes);
    }

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo
    {
        $memo->update($attributes);

        return $memo->refresh();
    }
}
