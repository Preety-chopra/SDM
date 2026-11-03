<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('actor:id,name,employee_number')
            ->when($filters['actor_id'] ?? null, fn (Builder $query, $id) => $query->where('actor_id', $id))
            ->when($filters['event'] ?? null, fn (Builder $query, $event) => $query->where('event', $event))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->where('created_at', '<', Carbon::parse($date)->addDay()->startOfDay()))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('event', 'like', '%'.$search.'%')
                        ->orWhere('auditable_type', 'like', '%'.$search.'%')
                        ->orWhere('auditable_id', $search)
                        ->orWhere('request_id', 'like', '%'.$search.'%')
                        ->orWhereHas('actor', fn (Builder $actor) => $actor
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('employee_number', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(50);
    }

    public function actors(): Collection
    {
        return User::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'employee_number']);
    }

    public function events(): Collection
    {
        return AuditLog::query()->distinct()->orderBy('event')->pluck('event');
    }
}
