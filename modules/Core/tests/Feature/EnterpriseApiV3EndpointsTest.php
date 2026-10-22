<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\EnterpriseApiV3Service;
use Tests\TestCase;

class EnterpriseApiV3EndpointsTest extends TestCase
{
    use RefreshDatabase;

    private EnterpriseApiV3Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseApiV3Service::class);
    }

    public function test_executive_kpis_endpoint_returns_json_and_headers(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => 'tenant-corp-alpha',
        ])->getJson('/api/v3/executive/kpis');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'tenant_id',
                'status',
                'active_modules_count',
                'open_incidents_count',
                'daily_throughput_idr',
                'rfc_7807_compliant',
            ],
            'meta' => [
                'api_version',
                'timestamp',
            ],
        ]);
        $this->assertSame('tenant-corp-alpha', $response->json('data.tenant_id'));
    }

    public function test_executive_snapshot_endpoint_returns_cluster_dr_and_post_quantum_state(): void
    {
        $response = $this->getJson('/api/v3/executive/snapshot');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'snapshot' => [
                'cluster_state' => 'HEALTHY',
                'rpo_seconds' => 0,
                'rto_seconds' => 0,
                'dr_quorum' => '3_OF_3_SYNCHRONIZED',
                'post_quantum_status' => 'READY',
            ],
        ]);
    }

    public function test_rfc_7807_problem_details_payload_structure(): void
    {
        $problem = $this->service->createProblemDetails(
            429,
            'Rate Limit Exceeded',
            'The client has sent more than 60 requests in one minute.',
            '/api/v3/executive/kpis',
            'ERR_RATE_LIMIT_EXCEEDED'
        );

        $this->assertSame(429, $problem['status']);
        $this->assertSame('Rate Limit Exceeded', $problem['title']);
        $this->assertSame('ERR_RATE_LIMIT_EXCEEDED', $problem['code']);
        $this->assertStringContainsString('https://api.autoserve.internal/v3/errors/', $problem['type']);
        $this->assertNotEmpty($problem['timestamp']);
    }

    public function test_idempotency_locking_mechanism(): void
    {
        $key = 'idemp-req-'.bin2hex(random_bytes(8));

        $firstAttempt = $this->service->lockIdempotencyKey($key, 60);
        $secondAttempt = $this->service->lockIdempotencyKey($key, 60);

        $this->assertTrue($firstAttempt);
        $this->assertFalse($secondAttempt);
    }
}
