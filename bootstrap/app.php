<?php

use App\Http\Middleware\AuditRequest;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->validateCsrfTokens(except: ['api/webhook/*']);

        $middleware->alias([
            'admin'       => EnsureAdmin::class,
            'super_admin' => EnsureSuperAdmin::class,
            'audit'       => AuditRequest::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {

    // Tangkap AuthenticationException secara EKSPLISIT dulu
    $exceptions->render(function (AuthenticationException $e, Request $request) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
        ], 401);
    });

    // Lalu handler umum untuk exception lainnya
    $exceptions->render(function (\Throwable $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource tidak ditemukan.',
                ], 404);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan.',
                ], 404);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method tidak diizinkan.',
                ], 405);
            }

            if (app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada server.',
                ], 500);
            }
        }
    });
})
    ->create();
