<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictStationControllerAccess
{
    /** @var list<string> */
    private const ALLOWED_ROUTES = [
        'sdm-memos.index',
        'sdm-memos.drafts',
        'sdm-memos.create',
        'sdm-memos.store',
        'sdm-memos.store-and-submit',
        'sdm-memos.show',
        'sdm-memos.edit',
        'sdm-memos.update',
        'sdm-memos.submit',
        'sdm-memos.destroy',
        'sdm-memos.station-manager.update',
        'sdm-memos.attachments.download',
        'sdm-memos.attachments.destroy',
        'sdm-memos.staff-signature.download',
        'sdm-memos.penalty-letter.download',
        'sdm-memos.objection.download',
        'sdm-memos.workflow-evidence.download',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isStationController()
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            abort(403);
        }

        return $next($request);
    }
}
