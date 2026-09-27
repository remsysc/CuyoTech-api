<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreshRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $role = $request->user()->role;
        }

        return $next($request);
    }
}
