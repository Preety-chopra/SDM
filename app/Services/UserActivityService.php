<?php

namespace App\Services;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class UserActivityService
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
        private AuditService $audit,
    ) {}

    public function listing(array $filters): array
    {
        return [
            'activities' => $this->logs->paginate($filters)->appends($filters),
            'actors' => $this->logs->actors(),
            'events' => $this->logs->events(),
            'filters' => $filters,
        ];
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
