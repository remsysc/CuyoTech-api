<?php

use App\Http\Middleware\CheckIsActive;
use App\Http\Middleware\EnsureFreshRole;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            'throttle:api',
            CheckIsActive::class,
            EnsureFreshRole::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => CheckIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() || $request->is('receipts/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->is('receipts/*')) {
                return response()->json([
                    'error' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->is('receipts/*')) {
                $message = $e->getMessage();
                $error = ($message && $message !== 'This action is unauthorized.') ? $message : 'UNAUTHORIZED_ROLE';

                return response()->json([
                    'error' => $error,
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->is('receipts/*')) {
                return response()->json([
                    'error' => 'VALIDATION_FAILED',
                    'fields' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->is('receipts/*')) {
                if ($e->getStatusCode() === 401) {
                    return response()->json([
                        'error' => $e->getMessage() ?: 'UNAUTHENTICATED',
                    ], 401);
                }

                if ($e->getStatusCode() === 403) {
                    return response()->json([
                        'error' => $e->getMessage() ?: 'UNAUTHORIZED_ROLE',
                    ], 403);
                }

                if ($e->getStatusCode() === 400 && $e->getMessage()) {
                    return response()->json([
                        'error' => $e->getMessage(),
                    ], 400);
                }

                if ($e->getStatusCode() === 404) {
                    return response()->json([
                        'error' => $e->getMessage() ?: 'NOT_FOUND',
                    ], 404);
                }

                if ($e->getStatusCode() === 409) {
                    return response()->json([
                        'error' => $e->getMessage() ?: 'CONFLICT',
                    ], 409);
                }

                if ($e->getStatusCode() === 422) {
                    return response()->json([
                        'error' => $e->getMessage() ?: 'VALIDATION_FAILED',
                    ], 422);
                }

                if ($e->getStatusCode() === 429) {
                    return response()->json([
                        'error' => 'RATE_LIMITED',
                    ], 429);
                }
            }
        });
    })->create();
