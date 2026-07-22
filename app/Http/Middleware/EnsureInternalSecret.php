<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the internal service-to-service admin API. Callers (the admin control
 * plane) must present the shared INTERNAL_API_SECRET; browsers never can.
 */
class EnsureInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('spurs.internal_secret');
        $provided = (string) $request->header('X-Internal-Secret', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
