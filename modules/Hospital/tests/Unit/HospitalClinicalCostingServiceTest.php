<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\HospitalClinicalCostingService;
use Tests\TestCase;

class HospitalClinicalCostingServiceTest extends TestCase
{
    private HospitalClinicalCostingService $costingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costingService = new HospitalClinicalCostingService;
    }

    public function test_procedure_unit_cost_calculation_with_surplus_margin(): void
    {
        $consumables = [
            ['item_code' => 'STENT-01', 'description' => 'Drug Eluting Stent', 'quantity' => 1.0, 'unit_cost_idr' => 7_500_000],
            ['item_code' => 'CATH-01', 'description' => 'Guiding Catheter & Wire', 'quantity' => 2.0, 'unit_cost_idr' => 450_000],
        ];

        $staffFees = [
            ['role' => 'SURGEON_DPJP', 'personnel_name' => 'dr. Jantung Sp.JP', 'fee_idr' => 4_000_000],
            ['role' => 'ANESTHESIOLOGIST', 'personnel_name' => 'dr. Anestesi Sp.An', 'fee_idr' => 1_500_000],
            ['role' => 'SCRUB_NURSE', 'personnel_name' => 'Ns. Bedah', 'fee_idr' => 500_000],
        ];

        // Benchmark INA-CBG Cath Lab Rp 32.000.000 (Rasio biaya ~60-70%)
        $benchmarkTariff = 32_000_000;

        $costSummary = $this->costingService->calculateProcedureUnitCost(
            consumables: $consumables,
            staffFees: $staffFees,
            primaryEquipmentCode: 'CATH_LAB_ANGIOGRAPHY',
            equipmentDurationMinutes: 60, // 60 menit * 45.000 = 2.7jt
            facilityRoomType: 'MAJOR_OPERATING_THEATER',
            facilityDurationHours: 2, // 2 jam * 750.000 = 1.5jt + 175rb waste = 1.675jt
            benchmarkInaCbgTariffIdr: $benchmarkTariff
        );

        $this->assertGreaterThan(0, $costSummary['total_unit_cost_idr']);
        $this->assertGreaterThan(0, $costSummary['variance_margin_idr']);
        $this->assertSame('HEALTHY_SURPLUS', $costSummary['profitability_status']);
        $this->assertLessThan(100.0, $costSummary['cost_to_tariff_ratio_pct']);
    }

    public function test_deficit_overspending_detection(): void
    {
        $consumables = [
            ['item_code' => 'EXPENSIVE-IMPLANT', 'description' => 'Custom Titanium Joint', 'quantity' => 1.0, 'unit_cost_idr' => 25_000_000],
        ];

        $staffFees = [
            ['role' => 'SURGEON', 'personnel_name' => 'dr. Ortopedi', 'fee_idr' => 8_000_000],
        ];

        // Benchmark tarif terlalu rendah (Rp 15.000.000)
        $costSummary = $this->costingService->calculateProcedureUnitCost(
            consumables: $consumables,
            staffFees: $staffFees,
            primaryEquipmentCode: 'STANDARD_OR_TABLE_MONITOR',
            equipmentDurationMinutes: 120,
            facilityRoomType: 'MAJOR_OPERATING_THEATER',
            facilityDurationHours: 3,
            benchmarkInaCbgTariffIdr: 15_000_000
        );

        $this->assertLessThan(0, $costSummary['variance_margin_idr']);
        $this->assertSame('DEFICIT_OVERSPENDING', $costSummary['profitability_status']);
        $this->assertGreaterThan(100.0, $costSummary['cost_to_tariff_ratio_pct']);
    }
}
