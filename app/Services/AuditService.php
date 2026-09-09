<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function __construct(private Request $request) {}

    public function record(Model $subject, ?User $actor, string $event, array $before = [], array $after = []): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'before_values' => $before === [] ? null : $before,
            'after_values' => $after === [] ? null : $after,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'request_id' => $this->request->header('X-Request-ID'),
        ]);
    }
}
