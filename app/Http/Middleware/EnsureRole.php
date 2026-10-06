<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $flatRoles = [];
        foreach ($roles as $role) {
            foreach (explode('|', $role) as $r) {
                $flatRoles[] = trim($r);
            }
        }

        if (! in_array($user->role, $flatRoles, true)) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        return $next($request);
    }
}
