<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AftCashCheckReportRepositoryInterface;
use App\Enums\UserPermission;
use App\Models\AftCashCheckReport;
use App\Models\Station;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AftCashCheckReportRepository implements AftCashCheckReportRepositoryInterface
{
    public function paginateFor(User $user, bool $pending = false): LengthAwarePaginator
    {
        return AftCashCheckReport::query()->with('currentStep')->withCount('entries')
            ->when(! $user->can(UserPermission::ViewAllMemos->value), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('created_by', $user->id)
                ->orWhereHas('steps', fn (Builder $query) => $query->where('assigned_to', $user->id))))
            ->when($pending, fn (Builder $query) => $query->whereHas('currentStep', fn (Builder $query) => $query->where('assigned_to', $user->id)->where('decision', 'pending')))
            ->latest('id')->paginate(15)->withQueryString();
    }

    public function pendingCountFor(User $user): int
    {
        return AftCashCheckReport::query()->whereHas('currentStep', fn (Builder $query) => $query
            ->where('assigned_to', $user->id)->where('decision', 'pending'))->count();
    }

    public function save(?AftCashCheckReport $report, array $attributes): AftCashCheckReport
    {
        $report ??= new AftCashCheckReport;
        $report->fill($attributes)->save();

        return $report;
    }

    public function findForUpdate(int $id): AftCashCheckReport
    {
        return AftCashCheckReport::query()->lockForUpdate()->findOrFail($id);
    }

    public function stationsForUpdate(array $ids): Collection
    {
        return Station::query()->with('metroLine')->whereKey($ids)->lockForUpdate()->get()->keyBy('id');
    }
}
