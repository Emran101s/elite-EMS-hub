<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assign (or honour) X-Request-Id and push it into the log context so every
 * structured log line for the request shares one correlation id.
 *
 * Lives under App\Support (monitoring wiring) rather than App\Http\Middleware
 * so Phase 1 tenancy middleware ownership stays with Claude.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get(self::HEADER) ?: (string) Str::uuid();
        $request->headers->set(self::HEADER, $id);

        Log::shareContext(['request_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
