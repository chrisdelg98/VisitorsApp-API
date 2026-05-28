<?php

use App\Http\Middleware\AutoCloseStaleVisits;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every request.
        $middleware->append(ForceHttps::class);
        $middleware->append(SecurityHeaders::class);

        // Route-level aliases.
        $middleware->alias([
            'api.key'            => ValidateApiKey::class,
            'auto-close-visits'  => AutoCloseStaleVisits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON responses for everything under /api/*.
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 422 — validation errors.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                    'code'    => 'VALIDATION_ERROR',
                ], 422);
            }
        });

        // 401 — missing/invalid auth (covers Sanctum guard rejections in Phase 5).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'code'    => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        // 403 — authenticated but not allowed.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to perform this action.',
                    'code'    => 'FORBIDDEN',
                ], 403);
            }
        });

        // 404 — unknown route OR resource not found via route model binding.
        // Laravel wraps ModelNotFoundException into NotFoundHttpException when it
        // happens during route binding, so we inspect the previous exception.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isModelNotFound = $e->getPrevious() instanceof ModelNotFoundException;

                return response()->json([
                    'success' => false,
                    'message' => $isModelNotFound ? 'Resource not found.' : 'Endpoint not found.',
                    'code'    => $isModelNotFound ? 'RESOURCE_NOT_FOUND' : 'ENDPOINT_NOT_FOUND',
                ], 404);
            }
        });

        // 405 — wrong HTTP method. Keep RFC-compliant Allow header.
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                    'code'    => 'METHOD_NOT_ALLOWED',
                ], 405, $e->getHeaders());
            }
        });

        // 429 — rate limit hit.
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'code'    => 'RATE_LIMITED',
                ], 429, $e->getHeaders());
            }
        });

        // Catch-all for any other HttpException (preserves original status code).
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'HTTP error.',
                    'code'    => 'HTTP_ERROR',
                ], $e->getStatusCode(), $e->getHeaders());
            }
        });
    })->create();
