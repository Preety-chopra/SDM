<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    public function __construct(private AuditService $audit) {}

    public function handle(Login|Logout|Failed $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $name = match (true) {
            $event instanceof Login => 'auth.login',
            $event instanceof Logout => 'auth.logout',
            default => 'auth.failed',
        };

        $this->audit->record($event->user, $event instanceof Failed ? null : $event->user, $name);
    }
}
