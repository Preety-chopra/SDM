<?php

namespace App\Services;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\UserActivityReportRepositoryInterface;
use App\Enums\WorkflowStepType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class UserActivityService
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
        private AuditService $audit,
        private UserActivityReportRepositoryInterface $report,
    ) {}

    public function listing(array $filters): array
    {
        $actors = $this->logs->actors();

        return [
            'activities' => $this->logs->paginate($filters)->appends($filters),
            'actors' => $actors,
            'events' => $this->logs->events(),
            'filters' => $filters,
            'hrSummary' => $this->hrSummary($filters, $actors),
        ];
    }

    private function hrSummary(array $filters, Collection $actors): array
    {
        $summary = $this->report->summary($filters);
        $empty = ['created' => 0, 'forwarded' => 0, 'forward_actions' => 0, 'approved' => 0];
        $totals = $empty;
        foreach ($summary['totals'] as $row) {
            $totals[$row->outcome] = (int) $row->memos;
            if ($row->outcome === 'forwarded') {
                $totals['forward_actions'] = (int) $row->actions;
            }
        }

        $levels = [];
        foreach (WorkflowStepType::cases() as $stage) {
            $levels[$stage->value] = ['label' => $stage->label(), ...$empty];
        }
        $levels['legacy_manager_operations'] = ['label' => 'Manager CMC (legacy workflow)', ...$empty];
        foreach ($summary['levels'] as $row) {
            if (! isset($levels[$row->stage])) {
                continue;
            }
            $levels[$row->stage][$row->outcome] = (int) $row->memos;
            if ($row->outcome === 'forwarded') {
                $levels[$row->stage]['forward_actions'] = (int) $row->actions;
            }
        }

        $actors = $actors->keyBy('id');
        $people = $summary['people']->groupBy('actor_id')->map(function (Collection $rows) use ($actors, $empty): array {
            $id = $rows->first()->actor_id;
            $actor = $actors->get($id);
            $person = ['id' => $id === null ? null : (int) $id, 'name' => $actor?->name ?? 'System / deleted user', 'employee_number' => $actor?->employee_number, ...$empty];
            foreach ($rows as $row) {
                $person[$row->outcome] = (int) $row->memos;
                if ($row->outcome === 'forwarded') {
                    $person['forward_actions'] = (int) $row->actions;
                }
            }

            return $person;
        })->sortBy([['name', 'asc'], ['id', 'asc']])->values();

        return ['totals' => $totals, 'levels' => $levels, 'people' => $people];
    }

    public function recordRequest(Request $request, User $actor, int $status): void
    {
        $route = $request->route();
        $ids = [];
        foreach ($route->parameters() as $key => $value) {
            $id = $value instanceof Model ? $value->getKey() : $value;
            // Only numeric resource identifiers are retained; route tokens and hashes are excluded.
            if (is_numeric($id)) {
                $ids[$key] = (int) $id;
            }
        }

        $this->audit->record($actor, $actor, 'activity.request', after: [
            'method' => $request->method(),
            'route' => $route->getName(),
            'resource' => $route->uri(),
            'resource_ids' => $ids,
            'status' => $status,
        ]);
    }
}
