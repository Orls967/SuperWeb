<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\FefoInventoryService;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use RuntimeException;
use Tests\TestCase;

class FefoInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private FefoInventoryService $fefoService;

    private PharmacyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fefoService = app(FefoInventoryService::class);

        $this->branch = PharmacyBranch::create([
            'branch_code' => 'PHARM-FEFO-01',
            'name' => 'Apotek Utama Surabaya',
            'type' => 'INTERNAL',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);
    }

    public function test_fefo_depletes_earliest_expiring_lot_first(): void
    {
        // Lot A: expires in 30 days, qty 50
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'AMOX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-EARLY',
            'quantity' => 50,
            'expiry_date' => now()->addDays(30)->toDateString(),
            'is_quarantined' => false,
        ]);

        // Lot B: expires in 90 days, qty 100
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'AMOX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-LATER',
            'quantity' => 100,
            'expiry_date' => now()->addDays(90)->toDateString(),
            'is_quarantined' => false,
        ]);

        // Request 70 units -> 50 from LOT-EARLY, 20 from LOT-LATER
        $picks = $this->fefoService->pickStock($this->branch->id, 'AMOX-500', 70);

        $this->assertCount(2, $picks);
        $this->assertSame('LOT-EARLY', $picks[0]['lot_number']);
        $this->assertSame(50, $picks[0]['picked_qty']);

        $this->assertSame('LOT-LATER', $picks[1]['lot_number']);
        $this->assertSame(20, $picks[1]['picked_qty']);

        // Check remaining lot quantities
        $this->assertSame(0, (int) PharmacyBranchLot::where('lot_number', 'LOT-EARLY')->value('quantity'));
        $this->assertSame(80, (int) PharmacyBranchLot::where('lot_number', 'LOT-LATER')->value('quantity'));
    }

    public function test_quarantined_lots_are_excluded_from_fefo_picking(): void
    {
        // Lot Q: earliest expiry but quarantined
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'CIPRO-500',
            'drug_name' => 'Ciprofloxacin 500mg',
            'lot_number' => 'LOT-CONTAMINATED',
            'quantity' => 100,
            'expiry_date' => now()->addDays(10)->toDateString(),
            'is_quarantined' => true,
        ]);

        // Lot Safe: later expiry and safe
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'CIPRO-500',
            'drug_name' => 'Ciprofloxacin 500mg',
            'lot_number' => 'LOT-SAFE',
            'quantity' => 40,
            'expiry_date' => now()->addDays(60)->toDateString(),
            'is_quarantined' => false,
        ]);

        $picks = $this->fefoService->pickStock($this->branch->id, 'CIPRO-500', 30);

        $this->assertCount(1, $picks);
        $this->assertSame('LOT-SAFE', $picks[0]['lot_number']);
        $this->assertSame(30, $picks[0]['picked_qty']);
    }

    public function test_insufficient_stock_throws_exception_without_deduction(): void
    {
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'INSULIN-GLAR',
            'drug_name' => 'Insulin Glargine',
            'lot_number' => 'LOT-INS-01',
            'quantity' => 10,
            'expiry_date' => now()->addDays(45)->toDateString(),
            'is_quarantined' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient FEFO stock');

        $this->fefoService->pickStock($this->branch->id, 'INSULIN-GLAR', 25);
    }
}
