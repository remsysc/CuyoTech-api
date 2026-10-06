<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($token = $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($token);

            if (! $accessToken) {
                $request->setUserResolver(fn () => null);
                auth()->guard('sanctum')->forgetUser();
                auth()->guard('web')->forgetUser();

                return $next($request);
            }

            $tokenable = $accessToken->tokenable;
            $user = $tokenable instanceof User ? $tokenable->fresh() : null;

            if ($user instanceof User) {
                $user = $user->withAccessToken($accessToken);
                $request->setUserResolver(fn () => $user);
                auth()->guard('sanctum')->setUser($user);
            }
        } else {
            $currentUser = $request->user() ?? $request->user('sanctum');
            $user = $currentUser instanceof User ? $currentUser->fresh() : null;
        }

        if ($user instanceof User && ! $user->is_active) {
            return response()->json([
                'error' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        return $next($request);
    }
}
