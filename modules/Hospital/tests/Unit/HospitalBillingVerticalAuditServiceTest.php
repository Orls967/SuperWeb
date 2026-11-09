<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\ClaimWaterfall;
use Modules\Hospital\Domain\Models\FolioItem;
use Modules\Hospital\Domain\Services\HospitalBillingVerticalAuditService;
use Tests\TestCase;

class HospitalBillingVerticalAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private HospitalBillingVerticalAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new HospitalBillingVerticalAuditService();
    }

    public function test_revenue_cycle_kpi_calculation_with_clean_metrics(): void
    {
        $episode = BillingEpisode::create([
            'episode_code' => 'EP-KPI-01',
            'encounter_id' => 1,
            'escrow_deposit_idr' => 2_000_000,
            'total_charges_idr' => 10_000_000,
            'bpjs_coverage_idr' => 8_000_000,
            'insurance_copay_idr' => 0,
            'patient_share_idr' => 2_000_000,
            'status' => 'SETTLED',
        ]);

        FolioItem::create([
            'billing_episode_id' => $episode->id,
            'item_category' => 'PROCEDURE',
            'description' => 'Paket Operasi',
            'quantity' => 1,
            'unit_price_idr' => 10_000_000,
            'subtotal_idr' => 10_000_000,
        ]);

        ClaimWaterfall::create([
            'billing_episode_id' => $episode->id,
            'step_order' => 1,
            'payer_type' => 'BPJS',
            'gross_billed_idr' => 10_000_000,
            'covered_amount_idr' => 8_000_000,
            'status' => 'SETTLED',
        ]);

        $kpis = $this->auditService->calculateRevenueCycleKpis(30);

        $this->assertSame(30, $kpis['period_days']);
        $this->assertGreaterThan(0, $kpis['total_billed_gross_idr']);
        $this->assertSame(0, $kpis['discrepancies_detected']);
        $this->assertGreaterThanOrEqual(0.0, $kpis['clean_claim_rate_pct']);
        $this->assertGreaterThanOrEqual(0.0, $kpis['net_collection_rate_pct']);
    }
}
