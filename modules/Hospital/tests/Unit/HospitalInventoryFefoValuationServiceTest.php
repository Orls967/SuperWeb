<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Services\HospitalInventoryFefoValuationService;
use Tests\TestCase;

class HospitalInventoryFefoValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private HospitalInventoryFefoValuationService $valuationService;
    private PharmacyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valuationService = new HospitalInventoryFefoValuationService();

        $this->branch = PharmacyBranch::firstOrCreate(
            ['branch_code' => 'PB-FEFO-01'],
            ['name' => 'Farmasi Uji FEFO', 'type' => 'HOSPITAL', 'city' => 'Surabaya', 'is_active' => true]
        );
    }

    public function test_fefo_allocation_draws_from_earliest_expiring_lot_first(): void
    {
        $drugCode = 'DRUG-INSULIN-GLARGINE';

        // Lot A: Expire dalam 60 hari (Lebih cepat)
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => $drugCode,
            'drug_name' => 'Insulin Glargine 100 IU/mL',
            'lot_number' => 'LOT-EXP-EARLY',
            'expiry_date' => now()->addDays(60),
            'quantity' => 10,
            'unit_cost_idr' => 200_000,
            'is_quarantined' => false,
        ]);

        // Lot B: Expire dalam 300 hari (Lebih lama)
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => $drugCode,
            'drug_name' => 'Insulin Glargine 100 IU/mL',
            'lot_number' => 'LOT-EXP-LATE',
            'expiry_date' => now()->addDays(300),
            'quantity' => 20,
            'unit_cost_idr' => 200_000,
            'is_quarantined' => false,
        ]);

        // Butuh 15 unit: Harus mengambil 10 dari LOT-EXP-EARLY dan 5 dari LOT-EXP-LATE
        $allocation = $this->valuationService->allocateStockFefo($drugCode, 15);

        $this->assertSame(15, $allocation['allocated_quantity']);
        $this->assertSame(0, $allocation['unfulfilled_quantity']);
        $this->assertCount(2, $allocation['allocated_batches']);
        $this->assertSame('LOT-EXP-EARLY', $allocation['allocated_batches'][0]['lot_number']);
        $this->assertSame(10, $allocation['allocated_batches'][0]['quantity_drawn']);
        $this->assertSame('LOT-EXP-LATE', $allocation['allocated_batches'][1]['lot_number']);
        $this->assertSame(5, $allocation['allocated_batches'][1]['quantity_drawn']);
    }

    public function test_inventory_valuation_and_spoilage_allowance_report(): void
    {
        $report = $this->valuationService->generateInventoryValuationReport();

        $this->assertArrayHasKey('total_inventory_value_idr', $report);
        $this->assertArrayHasKey('quarantined_lots_value_idr', $report);
        $this->assertArrayHasKey('estimated_spoilage_allowance_idr', $report);
    }
}
