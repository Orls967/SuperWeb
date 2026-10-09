<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SupplierFinanceCollaborativePlanningService;
use Tests\TestCase;

class SupplierFinanceCollaborativePlanningTest extends TestCase
{
    use RefreshDatabase;

    protected SupplierFinanceCollaborativePlanningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierFinanceCollaborativePlanningService::class);
    }

    public function test_supplier_registration_and_forecast_sharing_refusal_tier_downgrade(): void
    {
        // 1. Initial registration as PREFERRED tier (261.1)
        $sup = $this->service->registerSupplier(
            supplierCode: 'SUP-BATTERY-LITHIUM-01',
            supplierName: 'PT Nusantara Battery Chemicals',
            partnershipTier: 'PREFERRED',
            contractExpiryDate: now()->addYears(2)->toDateString(),
            isForecastSharingConsented: true
        );
        $this->assertEquals('PREFERRED', $sup->partnership_tier);
        $this->assertTrue((bool) $sup->is_forecast_sharing_consented);

        // 2. Refusal of forecast sharing gracefully downgrades tier without forceful access ban (261.5 Edge Case)
        $downgraded = $this->service->handleForecastSharingRefusal('SUP-BATTERY-LITHIUM-01');
        $this->assertEquals('STANDARD', $downgraded->partnership_tier);
        $this->assertFalse((bool) $downgraded->is_forecast_sharing_consented);
        $this->assertTrue((bool) $downgraded->portal_access_active); // Still active!
    }

    public function test_supplier_contract_expiry_revokes_portal_access(): void
    {
        // 1. Active supplier shares 12-month rolling forecast (261.1)
        $activeSup = $this->service->registerSupplier('SUP-ACTIVE', 'Active Nickel Corp', 'STRATEGIC', now()->addYear()->toDateString());
        $forecast = $this->service->shareRollingForecast('SUP-ACTIVE', 50000, 48000, 12);
        $this->assertEquals(50000, (int) $forecast->projected_demand_units);
        $this->assertEquals(48000, (int) $forecast->confirmed_capacity_units);

        // 2. Expired supplier contract revokes portal access and blocks sharing (261.4)
        $expiredSup = $this->service->registerSupplier('SUP-EXPIRED', 'Old Smelter Inc', 'STANDARD', now()->subDay()->toDateString());
        $this->service->verifyContractExpiryAndGateAccess('SUP-EXPIRED');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('portal access is inactive or expired');
        $this->service->shareRollingForecast('SUP-EXPIRED', 1000, 1000, 12);
    }

    public function test_scf_early_payment_calculation_and_pro_rata_pool_queueing(): void
    {
        // 1. Full liquidity in pool -> Approved payout ($50,000 face value with 4% discount = $48,000 payout <= $50,000) (261.2 & 261.4)
        $approved = $this->service->requestScfEarlyPayment(
            supplierCode: 'SUP-TIRES-01',
            invoiceCode: 'INV-TIRES-2026-99',
            invoiceFaceValueUsd: 50000.0,
            discountRatePct: 4.0,
            investorPoolCapacityUsd: 100000.0
        );
        $this->assertEquals(48000.0, (float) $approved->approved_payout_usd);
        $this->assertEquals('APPROVED', $approved->payout_status);
        $this->assertFalse((bool) $approved->is_pro_rata_allocated);

        // 2. Insufficient investor pool -> Queued pro-rata policy instead of silent rejection (261.6 Edge Case)
        $proRata = $this->service->requestScfEarlyPayment(
            supplierCode: 'SUP-STEEL-02',
            invoiceCode: 'INV-STEEL-2026-101',
            invoiceFaceValueUsd: 100000.0,
            discountRatePct: 5.0, // Requested: 95,000
            investorPoolCapacityUsd: 60000.0 // Capacity only 60,000!
        );
        $this->assertEquals(60000.0, (float) $proRata->approved_payout_usd);
        $this->assertEquals('QUEUED_PRO_RATA', $proRata->payout_status);
        $this->assertTrue((bool) $proRata->is_pro_rata_allocated);
    }

    public function test_spc_collaborative_quality_feed_and_bad_data_improvement_plan(): void
    {
        // 1. High data quality feed (95.0 >= 70.0) (261.3)
        $goodFeed = $this->service->recordSpcQualityFeed('SUP-GLASS-01', 'BATCH-GL-01', 98.5, 95.0);
        $this->assertFalse((bool) $goodFeed->improvement_plan_required);

        // 2. Poor data quality feed (55.0 < 70.0) triggers improvement plan (261.7)
        $badFeed = $this->service->recordSpcQualityFeed('SUP-RIVETS-02', 'BATCH-RV-02', 80.0, 55.0);
        $this->assertTrue((bool) $badFeed->improvement_plan_required);
    }

    public function test_supplier_procurement_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerSupplier('SUP-AUD-1', 'Audited Sup', 'STRATEGIC', now()->addYear()->toDateString());
        $this->service->shareRollingForecast('SUP-AUD-1', 1000, 1000);
        $this->service->requestScfEarlyPayment('SUP-AUD-1', 'INV-AUD', 1000.0, 5.0, 5000.0);
        $this->service->recordSpcQualityFeed('SUP-AUD-1', 'BATCH-1', 90.0, 85.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: poor feed without improvement plan
        DB::table('supplier_collaborative_spc_feeds')->insert([
            'feed_code' => 'SPC-BAD-UNFLAGGED',
            'supplier_code' => 'SUP-AUD-1',
            'sample_batch_code' => 'BATCH-ROGUE',
            'spc_quality_score' => 60.0,
            'data_quality_score' => 45.0, // < 70!
            'improvement_plan_required' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
