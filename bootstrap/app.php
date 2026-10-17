<?php

use App\Http\Middleware\ThrottleCmsApiByIp;
use App\Http\Middleware\VerifyCmsHmacSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'throttleCmsIp' => ThrottleCmsApiByIp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error pada request API (/api/*) dikembalikan sebagai JSON dengan
        // format konsisten dan STATUS ASLINYA (404, 405, 401, 403, 429, 422, ...),
        // bukan dipaksa menjadi 500. Hanya error yang tidak terduga yang menjadi 500.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Exception ini membawa respons yang SUDAH jadi (mis. 429 dari rate limiter yang
            // memakai ->response()). Teruskan apa adanya; membungkusnya lagi menjadi 500
            // membuang status dan header Retry-After yang benar.
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            $envelope = fn (int $status, string $message, mixed $errors = null) => response()->json([
                'success' => false,
                'status_code' => $status,
                'message' => $message,
                'errors' => $errors,
            ], $status);

            if ($e instanceof ModelNotFoundException) {
                return $envelope(404, 'Resource not found.');
            }

            if ($e instanceof ValidationException) {
                return $envelope(422, 'The given data was invalid.', $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return $envelope(401, 'Unauthenticated.');
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                $message = match (true) {
                    $status === 404 => 'Endpoint not found.',
                    $status === 405 => 'Method not allowed.',
                    $status === 403 => 'Forbidden.',
                    $status === 429 => 'Too many requests.',
                    default => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                };

                $response = $envelope($status, $message);

                // Pertahankan header penting (mis. Allow pada 405, Retry-After pada 429).
                foreach ($e->getHeaders() as $name => $value) {
                    $response->headers->set($name, $value);
                }

                return $response;
            }

            // Error tidak terduga: detail hanya ditampilkan saat debug aktif.
            // config() dipakai (bukan env()) agar tetap benar setelah config:cache.
            return $envelope(
                500,
                'Internal server error.',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.'
            );
        });
    })
    ->create();
