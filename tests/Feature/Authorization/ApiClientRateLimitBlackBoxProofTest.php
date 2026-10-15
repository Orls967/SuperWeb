<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Domain\Models\ApiClient;
use Tests\TestCase;

class ApiClientRateLimitBlackBoxProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_black_box_client_rate_limiting_and_isolation(): void
    {
        $service = app(IntegrationService::class);

        // Klien A dengan limit 3 per menit
        $clientAData = $service->registerApiClient('Client Alpha', 'CUSTOM');
        /** @var ApiClient $clientA */
        $clientA = $clientAData['client'];
        $clientA->update(['rate_limit_per_minute' => 3]);
        $keyA = $clientAData['raw_api_key'];

        // Klien B dengan limit 3 per menit
        $clientBData = $service->registerApiClient('Client Beta', 'CUSTOM');
        /** @var ApiClient $clientB */
        $clientB = $clientBData['client'];
        $clientB->update(['rate_limit_per_minute' => 3]);
        $keyB = $clientBData['raw_api_key'];

        // Klien A: request 1-3 berhasil (bukan 429)
        for ($i = 1; $i <= 3; $i++) {
            $resA = $this->withHeaders(['X-API-Key' => $keyA])
                ->getJson(route('api.v1.integration.ping'));
            $this->assertSame(200, $resA->status(), "Klien A request {$i} harus 200.");
        }

        // Klien A: request 4 harus 429 + Retry-After + JSON payload
        $resA4 = $this->withHeaders(['X-API-Key' => $keyA])
            ->getJson(route('api.v1.integration.ping'));
        $this->assertSame(429, $resA4->status(), 'Klien A request 4 harus 429.');
        $this->assertTrue($resA4->headers->has('Retry-After'), 'Header Retry-After harus ada.');
        $resA4->assertJsonStructure([
            'message',
            'client_id',
            'limit_per_minute',
            'retry_after',
        ]);
        $this->assertSame($clientA->client_id, $resA4->json('client_id'));
        $this->assertSame(3, $resA4->json('limit_per_minute'));

        // Klien B: TIDAK terpengaruh oleh limit Klien A (request 1 Klien B harus 200)
        $resB1 = $this->withHeaders(['X-API-Key' => $keyB])
            ->getJson(route('api.v1.integration.ping'));
        $this->assertSame(200, $resB1->status(), 'Klien B tidak boleh terpengaruh rate limit Klien A.');
    }

    public function test_default_rate_limit_for_client_without_custom_limit_is_600(): void
    {
        // Uji konfigurasi limiter 'api-client' tanpa custom limit
        $request = Request::create('/api/v1/integration/ping', 'GET');
        $request->headers->set('X-Client-ID', 'unregistered-client-999');

        $limiter = RateLimiter::limiter('api-client');
        $this->assertNotNull($limiter, 'Limiter api-client harus terdaftar.');

        $limit = $limiter($request);
        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(600, $limit->maxAttempts, 'Default limit untuk client tanpa kuota khusus harus 600 per menit.');
    }
}
