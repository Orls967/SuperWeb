<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Domain\Models\ApiClient;
use Tests\TestCase;

class ApiClientRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected IntegrationService $integrationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrationService = app(IntegrationService::class);
    }

    public function test_non_admin_cannot_access_integration_dashboard(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($nonAdmin)->get(route('integration.index'));
        $response->assertStatus(403);

        $webhooksResponse = $this->actingAs($nonAdmin)->get(route('integration.webhooks'));
        $webhooksResponse->assertStatus(403);
    }

    public function test_admin_can_access_integration_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('integration.index'));
        $response->assertOk();

        $webhooksResponse = $this->actingAs($admin)->get(route('integration.webhooks'));
        $webhooksResponse->assertOk();
    }

    public function test_unauthenticated_user_redirected_from_integration_dashboard(): void
    {
        $response = $this->get(route('integration.index'));
        $response->assertRedirect('/login');
    }

    public function test_api_client_rate_limiting_enforced_by_api_key(): void
    {
        $registered = $this->integrationService->registerApiClient('Tier Test Partner', 'CUSTOM');
        /** @var ApiClient $client */
        $client = $registered['client'];
        $rawApiKey = $registered['raw_api_key'];

        // Set low limit for testing
        $client->update(['rate_limit_per_minute' => 3]);

        // 3 requests allowed
        for ($i = 0; $i < 3; $i++) {
            $response = $this->withHeaders(['X-API-Key' => $rawApiKey])
                ->getJson(route('api.v1.integration.ping'));
            $response->assertOk()
                ->assertJson(['status' => 'OK']);
        }

        // Request ke-(n+1) mengembalikan 429 Too Many Requests + Retry-After
        $overflowResponse = $this->withHeaders(['X-API-Key' => $rawApiKey])
            ->getJson(route('api.v1.integration.ping'));

        $overflowResponse->assertStatus(429);
        $this->assertTrue($overflowResponse->headers->has('Retry-After'));
        $overflowResponse->assertJsonStructure([
            'message',
            'client_id',
            'limit_per_minute',
            'retry_after',
        ]);
        $this->assertSame($client->client_id, $overflowResponse->json('client_id'));
        $this->assertSame(3, $overflowResponse->json('limit_per_minute'));
    }

    public function test_api_client_rate_limiting_enforced_by_client_id_header(): void
    {
        $registered = $this->integrationService->registerApiClient('Direct Client ID Partner', 'SILVER');
        /** @var ApiClient $client */
        $client = $registered['client'];
        $client->update(['rate_limit_per_minute' => 2]);

        // 2 requests allowed
        for ($i = 0; $i < 2; $i++) {
            $res = $this->withHeaders(['X-Client-ID' => $client->client_id])
                ->getJson(route('api.v1.integration.ping'));
            $res->assertOk();
        }

        // 3rd request receives 429 with Retry-After header
        $overflow = $this->withHeaders(['X-Client-ID' => $client->client_id])
            ->getJson(route('api.v1.integration.ping'));

        $overflow->assertStatus(429);
        $this->assertTrue($overflow->headers->has('Retry-After'));
        $this->assertSame($client->client_id, $overflow->json('client_id'));
    }

    public function test_api_client_rate_limiter_resets_after_clearing(): void
    {
        $registered = $this->integrationService->registerApiClient('Reset Test Partner', 'SILVER');
        /** @var ApiClient $client */
        $client = $registered['client'];
        $client->update(['rate_limit_per_minute' => 1]);

        $first = $this->withHeaders(['X-Client-ID' => $client->client_id])
            ->getJson(route('api.v1.integration.ping'));
        $first->assertOk();

        $second = $this->withHeaders(['X-Client-ID' => $client->client_id])
            ->getJson(route('api.v1.integration.ping'));
        $second->assertStatus(429);

        // Reset limiter via natural window expiry
        $this->travel(61)->seconds();

        $third = $this->withHeaders(['X-Client-ID' => $client->client_id])
            ->getJson(route('api.v1.integration.ping'));
        $third->assertOk();
    }
}
