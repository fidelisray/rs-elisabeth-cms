<?php

namespace Tests\Feature\Api\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

/**
 * MVP-1.2: error pada /api/* harus memakai status aslinya dan tidak
 * membocorkan detail internal.
 */
class ApiErrorHandlingTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    private function assertEnvelope($response, int $status): void
    {
        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', $status)
            ->assertJsonStructure(['success', 'status_code', 'message', 'errors']);
    }

    public function test_wrong_method_returns_405_not_500(): void
    {
        $response = $this->withHmac()->postJson('/api/v1/cms/news');

        $this->assertEnvelope($response, 405);
    }

    public function test_unauthenticated_sanctum_route_returns_401_not_500(): void
    {
        $response = $this->getJson('/api/user');

        $this->assertEnvelope($response, 401);
    }

    public function test_unknown_endpoint_returns_404(): void
    {
        $response = $this->withHmac()->getJson('/api/v1/cms/tidak-ada');

        $this->assertEnvelope($response, 404);
    }

    public function test_missing_model_returns_404(): void
    {
        $response = $this->withHmac()->getJson('/api/v1/cms/news/slug-tidak-ada');

        $this->assertEnvelope($response, 404);
    }

    public function test_forbidden_returns_403(): void
    {
        Route::get('/api/_test/forbidden', fn () => abort(403, 'Dilarang.'));

        $response = $this->getJson('/api/_test/forbidden');

        $this->assertEnvelope($response, 403);
    }

    public function test_too_many_requests_returns_429(): void
    {
        Route::get('/api/_test/throttled', fn () => abort(429, 'Terlalu banyak.'));

        $response = $this->getJson('/api/_test/throttled');

        $this->assertEnvelope($response, 429);
    }

    public function test_validation_error_returns_422_with_field_errors(): void
    {
        Route::get('/api/_test/validate', function () {
            request()->validate(['q' => 'required']);
        });

        $response = $this->getJson('/api/_test/validate');

        $this->assertEnvelope($response, 422);
        $response->assertJsonPath('errors.q.0', fn ($v) => is_string($v));
    }

    public function test_unexpected_error_returns_500_without_leaking_details_when_debug_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_test/boom', fn () => throw new \RuntimeException('rahasia: password db = xyz'));

        $response = $this->getJson('/api/_test/boom');

        $this->assertEnvelope($response, 500);
        $this->assertStringNotContainsString('rahasia', $response->getContent());
        $this->assertStringNotContainsString('xyz', $response->getContent());
    }

    public function test_debug_flag_is_read_from_config_not_env(): void
    {
        // Setelah config:cache, env() mengembalikan null. Perilaku harus
        // mengikuti config('app.debug'), bukan env('APP_DEBUG').
        config(['app.debug' => true]);
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';

        Route::get('/api/_test/boom-debug', fn () => throw new \RuntimeException('detail-debug'));

        $response = $this->getJson('/api/_test/boom-debug');

        $this->assertEnvelope($response, 500);
        $this->assertStringContainsString('detail-debug', $response->getContent());
    }

    public function test_hmac_failure_still_returns_401_envelope(): void
    {
        $response = $this->getJson('/api/v1/cms/news');

        $this->assertEnvelope($response, 401);
    }
}
