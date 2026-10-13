<?php

namespace Tests\Unit;

use App\Services\WebFrontendWebhookService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebFrontendWebhookServiceTest extends TestCase
{
    private WebFrontendWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebFrontendWebhookService;

        Config::set('web_frontend.webhook_url', 'http://172.17.12.17:2301/api/internal/webhook/cache-invalidate');
        Config::set('web_frontend.cons_id', 'WEB-FRONTEND');
        Config::set('web_frontend.secret_key', 'test-secret-key-12345');
        Config::set('web_frontend.timeout', 5);
    }

    public function test_generate_signature_matches_hmac_sha256_specification(): void
    {
        $timestamp = '1791863766';
        $consId = 'WEB-FRONTEND';
        $rawBody = '{"module":"news","event":"content.updated","slug":"test-berita"}';
        $secretKey = 'test-secret-key-12345';

        $expectedMessage = $timestamp.$consId.$rawBody;
        $expectedSignature = base64_encode(hash_hmac('sha256', $expectedMessage, $secretKey, true));

        $actualSignature = $this->service->generateSignature($timestamp, $consId, $rawBody, $secretKey);

        $this->assertEquals($expectedSignature, $actualSignature);
    }

    public function test_dispatch_returns_false_when_config_is_missing(): void
    {
        Config::set('web_frontend.webhook_url', null);

        $result = $this->service->dispatch('news', 'content.updated', 'test-slug');

        $this->assertFalse($result);

        Config::set('web_frontend.webhook_url', 'http://example.com');
        Config::set('web_frontend.secret_key', '');

        $result = $this->service->dispatch('news', 'content.updated', 'test-slug');

        $this->assertFalse($result);
    }

    public function test_dispatch_sends_valid_http_request_and_headers(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Cache invalidated'], 200),
        ]);

        $result = $this->service->dispatch('news', 'content.updated', 'berita-terbaru');

        $this->assertTrue($result);

        Http::assertSent(function (Request $request) {
            $hasValidHeaders = $request->hasHeader('X-Cons-ID', 'WEB-FRONTEND')
                && $request->hasHeader('X-Timestamp')
                && $request->hasHeader('X-Signature')
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('Accept')[0] === 'application/json';

            $body = json_decode($request->body(), true);
            $hasValidPayload = $body['module'] === 'news'
                && $body['event'] === 'content.updated'
                && $body['slug'] === 'berita-terbaru'
                && !isset($body['timestamp']);

            // Validasi rumus signature
            $timestamp = $request->header('X-Timestamp')[0];
            $expectedSignature = base64_encode(
                hash_hmac('sha256', $timestamp.'WEB-FRONTEND'.$request->body(), 'test-secret-key-12345', true)
            );
            $signatureMatches = $request->header('X-Signature')[0] === $expectedSignature;

            return $hasValidHeaders && $hasValidPayload && $signatureMatches;
        });
    }

    public function test_dispatch_throws_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Forbidden'], 403),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->dispatch('articles', 'content.updated', 'artikel-test');
    }

    public function test_dispatch_handles_exceptions_gracefully(): void
    {
        Http::fake(function () {
            throw new \Exception('Network connection timeout');
        });

        $this->expectException(\Exception::class);

        $this->service->dispatch('promotions', 'content.updated', 'promo-test');
    }
}
