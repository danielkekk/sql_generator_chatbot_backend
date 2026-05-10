<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'llm.rate_limit' => \App\Http\Middleware\LlmRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => 'validation_error',
                    'messages' => $e->errors(),
                ], 422);
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'error' => 'unauthenticated',
                ], 401);
            }

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenBlacklistedException ||
                $e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException ||
                $e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException ||
                $e instanceof \Tymon\JWTAuth\Exceptions\JWTException) {
                return response()->json([
                    'error' => 'unauthenticated',
                ], 401);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                return response()->json([
                    'error' => $e->getMessage() ?: 'http_error',
                ], $e->getStatusCode());
            }

            return response()->json([
                'error' => 'internal_server_error' . $e->getMessage(),
            ], 500);
        });
    })->create();
