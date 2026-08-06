<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the signed-in user's tenant for the duration of the request.
 *
 * Registered globally in bootstrap/app.php rather than attached per route, and
 * that is the whole point: a per-route opt-in is something somebody forgets on
 * the one route that matters, and an unbound request currently means an
 * unfiltered one. Global registration means there is nothing to remember.
 *
 * Unauthenticated requests stay unbound on purpose. The two public routes —
 * registration and badge check-in — are reached by token and already resolve a
 * single event from it, so they never enumerate anything to filter. When slice
 * 4 makes unbound queries throw, those two get an explicit binding taken from
 * the event the token resolves to.
 *
 * Forgetting afterwards matters more than it looks: Tenancy is static state,
 * and in a long-lived worker (Octane, a queue process) a leftover binding would
 * be inherited by whatever runs next.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            Tenancy::use($user->tenant_id);
        }

        try {
            return $next($request);
        } finally {
            Tenancy::forget();
        }
    }
}
