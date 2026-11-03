<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserActivityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserActivity
{
    public function __construct(private UserActivityService $activities) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('activity_actor', $request->user());

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $actor = $request->attributes->get('activity_actor') ?? $request->user();
        if ($actor instanceof User && $request->route() !== null) {
            $this->activities->recordRequest($request, $actor, $response->getStatusCode());
        }
    }
}
