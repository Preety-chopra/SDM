<?php

use App\Http\Middleware\EnsureUserIsEnabled;
use App\Http\Middleware\RecordUserActivity;
use App\Http\Middleware\RestrictStationControllerAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', RecordUserActivity::class);
        $middleware->appendToGroup('web', RestrictStationControllerAccess::class);
        $middleware->alias([
            'enabled' => EnsureUserIsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->isMethodSafe()
                || ! $request->routeIs('sdm-memos.*')
                || $request->is('api/*')
                || $request->expectsJson()
                || $response->getStatusCode() < 400) {
                return $response;
            }

            $message = match ($response->getStatusCode()) {
                403 => 'You do not have permission to perform this SDM action.',
                404 => 'The requested SDM could not be found. Refresh your SDM list before trying again.',
                419 => 'Your session has expired. Refresh the page and sign in again before retrying the SDM action.',
                429 => 'Too many requests. Please wait a moment before trying the SDM action again.',
                default => 'An error occurred while performing the SDM action. Check the SDM status before trying again.',
            };

            $response->setContent(view('errors.sdm-action', ['message' => $message])->render());

            return $response;
        });
    })->create();
