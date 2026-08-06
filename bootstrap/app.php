<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global is correct here: only stamps X-Request-Id + Log context from
        // the raw request/response. Needs no session or $request->user().
        // (Contrast ResolveTenant below — that one must wait for 'web' auth.)
        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        // Global, not per-route. A per-route opt-in is the thing somebody
        // forgets on the one route that matters, and an unbound request is
        // currently an unfiltered one. See App\Support\Tenancy.
        //
        // Appended to the global stack, which runs OUTSIDE the 'web' group —
        // before StartSession/Authenticate resolve $request->user(). Pushed
        // onto the 'web' group instead (not appended globally) so it runs
        // after the session/auth middleware that makes the user available.
        $middleware->web(append: [ResolveTenant::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
