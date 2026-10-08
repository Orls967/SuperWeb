<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\KamPartnershipRevenueService;
use Tests\TestCase;

class KamPartnershipRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected KamPartnershipRevenueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KamPartnershipRevenueService::class);
    }

    public function test_create_key_account_and_multi_line_bundle(): void
    {
        // 1. Create Key Account (247.1)
        $account = $this->service->createKeyAccount(
            accountCode: 'KAM-CONGLO-01',
            clientName: 'Nusantara Global Conglomerate',
            accountTier: 'GLOBAL_CONGLOMERATE',
            leadAccountDirector: 'DIRECTOR_VICTORIA'
        );

        $this->assertEquals('KAM-CONGLO-01', $account->account_code);
        $this->assertEquals('GLOBAL_CONGLOMERATE', $account->account_tier);

        // 2. Create Multi-Line Solution Bundle (247.2 & 247.5)
        $bundle = $this->service->createMultiLineBundle(
            accountId: (int) $account->id,
            bundleName: 'Enterprise Connectivity & Hospitality Package',
            lineComponents: [
                ['business_line' => 'TELECOM', 'price' => 300000.0, 'cogs' => 180000.0],   // margin: 120,000
                ['business_line' => 'HOTEL', 'price' => 200000.0, 'cogs' => 110000.0],     // margin: 90,000
                ['business_line' => 'INSURANCE', 'price' => 100000.0, 'cogs' => 50000.0],  // margin: 50,000
            ],
            contractEndDate: now()->addMonths(6)->toDateString()
        );

        // Total price = 600,000; Total margin = 260,000
        $this->assertEquals(600000.0, (float) $bundle->total_contract_price_usd);
        $this->assertEquals(260000.0, (float) $bundle->internal_margin_usd);
        $this->assertEquals('ACTIVE', $bundle->status);
    }

    public function test_partial_line_cancellation_triggers_recalculation(): void
    {
        $account = $this->service->createKeyAccount('KAM-02', 'Client B', 'STRATEGIC_ENTERPRISE', 'DIR_A');
        $bundle = $this->service->createMultiLineBundle(
            accountId: (int) $account->id,
            bundleName: 'Tri-Line Corporate Package',
            lineComponents: [
                ['business_line' => 'TELECOM', 'price' => 300000.0, 'cogs' => 180000.0],
                ['business_line' => 'HOTEL', 'price' => 200000.0, 'cogs' => 110000.0],
                ['business_line' => 'INSURANCE', 'price' => 100000.0, 'cogs' => 50000.0],
            ],
            contractEndDate: now()->addMonths(12)->toDateString()
        );

        // Cancel Insurance line only (247.6 Edge Case)
        $recalculated = $this->service->handlePartialBundleCancellation((int) $bundle->id, 'INSURANCE');

        // Status updated to RECALCULATED, remaining price = 500,000, margin = 210,000
        $this->assertEquals('RECALCULATED', $recalculated->status);
        $this->assertEquals(500000.0, (float) $recalculated->total_contract_price_usd);
        $this->assertEquals(210000.0, (float) $recalculated->internal_margin_usd);

        // Verify Insurance marked cancelled in database
        $this->assertDatabaseHas('kam_bundle_line_components', [
            'bundle_id' => $bundle->id,
            'business_line' => 'INSURANCE',
            'is_cancelled' => true,
        ]);
    }

    public function test_renewal_gap_alerts_90_60_30_days(): void
    {
        $account = $this->service->createKeyAccount('KAM-03', 'Client C', 'TIER_1_GOV', 'DIR_B');

        // Contract expiring in 25 days (triggers 30-day alert) (247.7)
        $bundle = $this->service->createMultiLineBundle(
            accountId: (int) $account->id,
            bundleName: 'Expiring Gov Suite',
            lineComponents: [
                ['business_line' => 'TELECOM', 'price' => 10000.0, 'cogs' => 5000.0],
            ],
            contractEndDate: now()->addDays(25)->toDateString()
        );

        $alert = $this->service->checkRenewalGapAlerts((int) $bundle->id);

        $this->assertTrue($alert['requires_follow_up']);
        $this->assertEquals(30, $alert['renewal_alert_level']);
        $this->assertEquals('ACCOUNT_DIRECTOR_FOLLOW_UP', $alert['action_owner']);
    }

    public function test_partner_revenue_share_calculation(): void
    {
        // Deal value $500,000, referral share 8% -> Payout $40,000 (247.4 & 247.5)
        $share = $this->service->calculatePartnerRevenueShare(
            partnerId: 'PARTNER-CHANNEL-ALPHA',
            dealValueUsd: 500000.0,
            sharePct: 8.00
        );

        $this->assertEquals(500000.0, (float) $share->deal_value_usd);
        $this->assertEquals(8.00, (float) $share->share_pct);
        $this->assertEquals(40000.0, (float) $share->calculated_payout_usd);
        $this->assertEquals('PENDING', $share->payout_status);
    }

    public function test_kam_partnership_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $account = $this->service->createKeyAccount('KAM-OK', 'OK Client', 'TIER_1_GOV', 'DIR_Z');
        $bundle = $this->service->createMultiLineBundle((int) $account->id, 'Bundle OK', [
            ['business_line' => 'EPC', 'price' => 1000.0, 'cogs' => 600.0],
        ], now()->addMonths(6)->toDateString());
        $this->service->calculatePartnerRevenueShare('P-1', 1000.0, 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: bundle internal margin doesn't match components
        DB::table('kam_multi_line_bundles')
            ->where('id', $bundle->id)
            ->update(['internal_margin_usd' => 999999.0]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
