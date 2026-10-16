<?php

use App\Console\Commands\RefreshGlossaryCache;
use App\Http\Middleware\VerifyCmsHmacSignature;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Daftarkan alias untuk middleware API Key CMS
        $middleware->alias([
            'verifyCmsHmac' => VerifyCmsHmacSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pastikan semua error pada request API (/api/*) selalu dikembalikan
        // dalam format JSON yang konsisten, bukan halaman HTML error bawaan Laravel.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof ModelNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 404,
                        'message' => 'Resource not found.',
                        'errors' => null,
                    ], 404);
                }

                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 404,
                        'message' => 'Endpoint not found.',
                        'errors' => null,
                    ], 404);
                }

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 422,
                        'message' => 'The given data was invalid.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                // Catch-all handler for 500 Internal Server Error
                return response()->json([
                    'success' => false,
                    'status_code' => 500,
                    'message' => 'Internal server error.',
                    'errors' => env('APP_DEBUG') ? $e->getMessage() : 'An unexpected error occurred.',
                ], 500);
            }
        });
    })
    ->withCommands([
        RefreshGlossaryCache::class,
    ])->create();
