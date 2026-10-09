<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\PlatformEconomyService;
use Tests\TestCase;

/**
 * Fase 147 — Platform Economy Tests
 *
 * Covers:
 *  (a) tier rate limit dihormati
 *  (b) white-label instance zero cross-tenant leak
 *  (c) revenue API = usage × tarif
 *  (d) deprecation lama → client v2 masih jalan dalam window
 *  (e) api:audit = 0 selisih
 */
class PlatformEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformEconomyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformEconomyService::class);
    }

    /**
     * (a) Tier rate limit dihormati.
     */
    public function test_developer_tier_rate_limits_enforced(): void
    {
        $freeDev = $this->service->registerDeveloper('Free Dev', 'free@example.com', 'FREE');
        $proDev = $this->service->registerDeveloper('Pro Dev', 'pro@example.com', 'PRO');

        $this->assertSame(60, $freeDev->rate_limit_per_min);
        $this->assertSame(600, $proDev->rate_limit_per_min);

        // Within limit
        $this->assertTrue($this->service->checkRateLimit($freeDev->developer_code, 59));
        $this->assertTrue($this->service->checkRateLimit($freeDev->developer_code, 60));

        // Over limit
        $this->assertFalse($this->service->checkRateLimit($freeDev->developer_code, 61));

        // Pro can handle 500
        $this->assertTrue($this->service->checkRateLimit($proDev->developer_code, 500));
        $this->assertFalse($this->service->checkRateLimit($proDev->developer_code, 601));
    }

    /**
     * (b) White-label instance zero cross-tenant leak.
     */
    public function test_white_label_isolated_multi_tenant_no_leak(): void
    {
        $tenantA = $this->service->createWhiteLabelTenant('Grand Aston Luxury', 'HOTEL_PMS', 15000000.00);
        $tenantB = $this->service->createWhiteLabelTenant('Klinik Sehat Prima', 'HEALTH_EMR', 8500000.00);

        $this->assertNotSame($tenantA->tenant_code, $tenantB->tenant_code);
        $this->assertNotSame($tenantA->isolated_schema_or_prefix, $tenantB->isolated_schema_or_prefix);
        $this->assertStringContainsString('grand-aston', $tenantA->isolated_schema_or_prefix);
        $this->assertStringContainsString('klinik-sehat', $tenantB->isolated_schema_or_prefix);

        $this->assertDatabaseHas('pe_white_label_tenants', [
            'tenant_code' => $tenantA->tenant_code,
            'solution_type' => 'HOTEL_PMS',
        ]);
        $this->assertDatabaseHas('pe_white_label_tenants', [
            'tenant_code' => $tenantB->tenant_code,
            'solution_type' => 'HEALTH_EMR',
        ]);
    }

    /**
     * (c) Revenue API = usage × tarif.
     */
    public function test_api_revenue_calculation_accurate(): void
    {
        $proDev = $this->service->registerDeveloper('Acme Corp', 'billing@acme.com', 'PRO');

        // PRO rate = 15.00 per 1k calls
        // 20,000 calls => (20,000 / 1,000) * 15 = 300.00
        $log = $this->service->recordApiUsage(
            $proDev->developer_code,
            '/api/v3/hotel/reservations',
            'HTL',
            20000
        );

        $this->assertEquals(300.00, (float) $log->billed_amount);
        $this->assertNotNull($log->ledger_reference);

        $this->assertDatabaseHas('pe_api_usage_logs', [
            'developer_code' => $proDev->developer_code,
            'calls_count' => 20000,
            'billed_amount' => 300.0000,
        ]);
    }

    /**
     * (d) Deprecation lama → client v2 masih jalan dalam window.
     */
    public function test_deprecation_lifecycle_client_works_in_window_then_sunsets(): void
    {
        // Deprecated endpoint with sunset in 30 days
        $futureSunset = Carbon::now()->addDays(30);
        $this->service->registerDeprecation('v2', '/api/v2/logistics/track', $futureSunset);

        // Still active inside window
        $this->assertTrue($this->service->isEndpointAvailable('v2', '/api/v2/logistics/track'));

        // Sunset endpoint (past)
        $pastSunset = Carbon::now()->subDays(1);
        $this->service->registerDeprecation('v1', '/api/v1/legacy/auth', $pastSunset);

        // Not available anymore
        $this->assertFalse($this->service->isEndpointAvailable('v1', '/api/v1/legacy/auth'));
    }

    /**
     * App certification and embedded finance test.
     */
    public function test_marketplace_certification_and_embedded_finance(): void
    {
        $dev = $this->service->registerDeveloper('Fintech Partner', 'partner@fintech.id', 'PRO');

        // Marketplace certification
        $app = $this->service->registerMarketplaceApp($dev->developer_code, 'Quick POS Sync', 'POS_VENDOR');
        $this->assertFalse((bool) $app->is_listed);

        $certified = $this->service->certifyApp($app->app_code, true, true);
        $this->assertTrue((bool) $certified->is_listed);
        $this->assertSame('CERTIFIED', $certified->certification_status);

        // Embedded finance
        $ef = $this->service->processEmbeddedFinance($dev->developer_code, 'PAYMENT', 1000000.00, 2.5);
        $this->assertEquals(25000.00, (float) $ef->platform_fee);
        $this->assertEquals(975000.00, (float) $ef->partner_fee);
        $this->assertEquals(1000000.00, (float) $ef->gross_amount);

        // Bug bounty
        $bounty = $this->service->reportBugBounty('researcher@whitehat.org', 'CRITICAL', 5000000.00);
        $this->assertSame('PAID', $bounty->payout_status);
        $this->assertNotNull($bounty->ledger_payout_ref);
    }

    /**
     * (e) api:audit = 0 selisih.
     */
    public function test_api_audit_command_passes_with_zero_discrepancy(): void
    {
        // Seed valid transactions
        $dev = $this->service->registerDeveloper('Audit Dev', 'audit@dev.com', 'PRO');
        $this->service->recordApiUsage($dev->developer_code, '/api/v3/iot/telemetry', 'MIN', 5000);
        $this->service->createWhiteLabelTenant('Sim Resto', 'RESTO_POS', 5000000.00);
        $this->service->processEmbeddedFinance($dev->developer_code, 'ESCROW', 500000.00, 2.0);

        $audit = $this->service->audit();
        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame('HEALTHY', $audit['status']);

        $this->artisan('api:audit')
            ->expectsOutputToContain('api:audit PASSED (0 discrepancies)')
            ->assertExitCode(0);
    }
}
