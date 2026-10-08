<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ProductStewardshipEprService;
use Tests\TestCase;

class ProductStewardshipEprTest extends TestCase
{
    use RefreshDatabase;

    protected ProductStewardshipEprService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductStewardshipEprService::class);
    }

    public function test_product_passport_grounding_and_repairability_score(): void
    {
        // 1. High repairability score (8.5) backed by verified BOM spare parts succeeds (289.1, 289.2, 289.8)
        $passport = $this->service->registerProductPassport(
            passportCode: 'PASSPORT-EV-BATTERY-PACK-01',
            sku: 'SKU-EV-BATTERY-MODULAR',
            carbonKgCo2e: 45.2,
            repairabilityScore: 8.5,
            recyclabilityPct: 94.0,
            bomSparePartsAvailable: true
        );
        $this->assertEquals(8.5, (float) $passport->repairability_score);
        $this->assertStringContainsString('https://autoserve.corp/passport/', $passport->public_qr_view_url);

        // 2. High repairability score (> 7.0) without available BOM spare parts rejected (289.8 Ground truth)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repairability score ground truth failure');
        $this->service->registerProductPassport('PASSPORT-UNSUPPORTED', 'SKU-SMARTPHONE-UNFIXABLE', 25.0, 9.0, 80.0, false);
    }

    public function test_epr_packaging_obligation_calculation(): void
    {
        // 100,000 units sold with 0.05 kg packaging = 5,000 kg total plastic obligation (289.3 & 289.5)
        // 5,000 kg * $0.15/kg = $750.00 fee liability
        $epr = $this->service->calculateEprObligation(
            obligationCode: 'EPR-PACKAGING-2026-Q3',
            reportingQuarter: '2026-Q3',
            unitsSold: 100000,
            packagingWeightKgPerUnit: 0.0500,
            feePerKgUsd: 0.1500
        );

        $this->assertEquals(5000.0, (float) $epr->total_plastic_obligation_kg);
        $this->assertEquals(750.0, (float) $epr->total_fee_liability_usd);
    }

    public function test_product_recall_remedy_and_unreachable_owner_liability(): void
    {
        // 1. Issue recall for 1,000 brake caliper units (289.4)
        $this->service->issueProductRecall('RECALL-BRAKE-CALIPER-01', 'SKU-BRAKE-CALIPER-V2', 'HIGH', 1000);

        // 2. 800 contacted and remedied; 200 unreachable -> accrued contingent liability for 200 units @ $120 = $24,000 (289.4 & 289.6 Edge Case)
        $processed = $this->service->processRecallRemedies(
            recallCode: 'RECALL-BRAKE-CALIPER-01',
            contactedUnits: 800,
            remediedUnits: 800,
            remedyCostPerUnitUsd: 120.0
        );

        $this->assertEquals('IN_PROGRESS', $processed->status);
        $this->assertEquals(24000.0, (float) $processed->unreachable_liability_usd);
    }

    public function test_product_stewardship_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerProductPassport('PASS-AUD', 'SKU-1', 10.0, 5.0, 80.0, false);
        $this->service->calculateEprObligation('EPR-AUD', '2026-Q1', 100, 0.1, 0.15);
        $this->service->issueProductRecall('REC-AUD', 'SKU-1', 'MODERATE', 10);
        $this->service->processRecallRemedies('REC-AUD', 10, 10);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: recall with uncontacted units but 0 accrued liability
        DB::table('product_safety_recalls')->insert([
            'recall_code' => 'REC-UNACCRUED-DISCREPANCY',
            'product_sku' => 'SKU-FAULTY',
            'defect_severity' => 'CRITICAL',
            'affected_units_count' => 500,
            'contacted_units_count' => 100,
            'remedied_units_count' => 100,
            'unreachable_liability_usd' => 0.0, // Discrepancy!
            'status' => 'IN_PROGRESS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
