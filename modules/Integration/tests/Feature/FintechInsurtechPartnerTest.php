<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\FintechInsurtechPartnerService;
use Tests\TestCase;

class FintechInsurtechPartnerTest extends TestCase
{
    use RefreshDatabase;

    protected FintechInsurtechPartnerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FintechInsurtechPartnerService::class);
    }

    public function test_fintech_partner_smart_routing_and_failover(): void
    {
        // 1. Primary route: BCA (unhealthy), Secondary route: Mandiri (healthy) (264.1 & 264.4)
        $this->service->registerFintechRoute('ROUTE-ID-BCA', 'ID', 'BCA_GATEWAY', 0.8, 99.0, false, 1);
        $this->service->registerFintechRoute('ROUTE-ID-MANDIRI', 'ID', 'MANDIRI_GATEWAY', 1.0, 98.5, true, 2);

        // 2. Automated failover routes to Mandiri
        $tx = $this->service->routeTransaction('ID', 50000.0);

        $this->assertEquals('BCA_GATEWAY', $tx->primary_partner);
        $this->assertEquals('MANDIRI_GATEWAY', $tx->actual_routed_partner);
        $this->assertTrue((bool) $tx->failover_occurred);
        $this->assertEquals('SETTLED', $tx->status);
    }

    public function test_all_partners_down_triggers_degraded_safe_hold(): void
    {
        // All routes down (264.5 Edge Case)
        $this->service->registerFintechRoute('ROUTE-SG-DBS', 'SG', 'DBS_PAY', 0.5, 99.0, false, 1);
        $this->service->registerFintechRoute('ROUTE-SG-OCBC', 'SG', 'OCBC_PAY', 0.6, 99.0, false, 2);

        $tx = $this->service->routeTransaction('SG', 12000.0);

        $this->assertTrue((bool) $tx->is_degraded_held);
        $this->assertEquals('HELD_DEGRADED', $tx->status);
        $this->assertEquals('NONE_AVAILABLE', $tx->actual_routed_partner);
    }

    public function test_daily_settlement_reconciliation_and_aging_exception(): void
    {
        // 1. Matched settlement: 0 variance (264.4)
        $matched = $this->service->reconcileDailySettlement('GOPAY', '2026-10-08', 250000.0, 250000.0);
        $this->assertEquals(0.0, (float) $matched->variance_amount_usd);
        $this->assertFalse((bool) $matched->is_exception);

        // 2. Mismatched settlement flagged as exception with aging (264.7)
        $unmatched = $this->service->reconcileDailySettlement('OVO', '2026-10-08', 180000.0, 175000.0, 3);
        $this->assertEquals(5000.0, (float) $unmatched->variance_amount_usd);
        $this->assertTrue((bool) $unmatched->is_exception);
        $this->assertEquals(3, (int) $unmatched->exception_aging_days);
    }

    public function test_open_finance_consent_grant_and_instant_revocation(): void
    {
        // 1. Grant consent (264.3)
        $consent = $this->service->grantOpenFinanceConsent('USER-99', 'FINTECH-LENDER-CREDIT', 'CREDIT_SCORE');
        $this->assertTrue($this->service->verifyPartnerDataAccess($consent->consent_token));

        // 2. Revoke consent immediately cuts off access (264.3 & 264.4)
        $revoked = $this->service->revokeOpenFinanceConsent($consent->consent_token);
        $this->assertFalse((bool) $revoked->is_active);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertFalse($this->service->verifyPartnerDataAccess($consent->consent_token));
    }

    public function test_fintech_treasury_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerFintechRoute('ROUTE-AUD', 'ID', 'PARTNER-OK', 1.0, 99.0, true, 1);
        $this->service->routeTransaction('ID', 100.0);
        $this->service->reconcileDailySettlement('PARTNER-OK', '2026-10-08', 100.0, 100.0);
        $c = $this->service->grantOpenFinanceConsent('USER-AUD', 'PARTNER-OK', 'DATA');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: settlement variance not flagged as exception
        DB::table('fintech_settlement_reconciliations')->insert([
            'settlement_code' => 'SETTLE-UNFLAGGED',
            'partner_name' => 'ROGUE-GATEWAY',
            'settlement_date' => '2026-10-08',
            'internal_ledger_amount_usd' => 1000.0,
            'partner_statement_amount_usd' => 500.0,
            'variance_amount_usd' => 500.0,
            'is_exception' => false, // Discrepancy!
            'exception_aging_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
