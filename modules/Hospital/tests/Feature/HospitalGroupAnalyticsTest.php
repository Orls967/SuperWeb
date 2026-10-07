<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\HospitalGroupAnalyticsService;
use Tests\TestCase;

class HospitalGroupAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalGroupAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalGroupAnalyticsService::class);
    }

    public function test_110_1_and_110_5_group_scorecard_calculation(): void
    {
        $scorecard = $this->service->computeGroupScorecard(
            hospitalCode: 'RS-UTAMA-01',
            hospitalName: 'RS Medika Nusantara Pusat',
            year: 2026,
            month: 10,
            admissions: 1200,
            mortalityRate: 1.2,
            readmissionRate: 3.5,
            patientSatisfaction: 4.8,
            revenueIdr: 45_000_000_000,
            bpjsArIdr: 12_000_000_000,
            insuranceArIdr: 8_500_000_000
        );

        $this->assertEquals('SC-RS-UTAMA-01-202610', $scorecard->scorecard_code);
        $this->assertEquals(1200, $scorecard->total_admissions);
        $this->assertEquals(45_000_000_000, $scorecard->total_revenue_idr);
        $this->assertGreaterThan(70, $scorecard->overall_quality_score);
    }

    public function test_110_3_equipment_roi_tracking(): void
    {
        // 1. Capex 15 Milyar IDR for 3T MRI, 1st month rev 1.5 Milyar IDR
        $roi1 = $this->service->trackEquipmentRoi(
            equipmentCode: 'MRI-3T-001',
            modality: 'MRI_3T',
            capexIdr: 15_000_000_000,
            additionalRevenueIdr: 1_500_000_000,
            proceduresDone: 250
        );

        $this->assertEquals(10.0, (float) $roi1->roi_percentage);
        $this->assertEquals(250, $roi1->total_procedures_done);

        // 2. Add second month rev 3.0 Milyar IDR
        $roi2 = $this->service->trackEquipmentRoi(
            equipmentCode: 'MRI-3T-001',
            modality: 'MRI_3T',
            capexIdr: 15_000_000_000,
            additionalRevenueIdr: 3_000_000_000,
            proceduresDone: 500
        );

        $this->assertEquals(30.0, (float) $roi2->roi_percentage);
        $this->assertEquals(750, $roi2->total_procedures_done);
    }
}
