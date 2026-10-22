<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\PharmacyMultiBranchRoutingService;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Tests\TestCase;

class PharmacyMultiBranchRoutingTest extends TestCase
{
    use RefreshDatabase;

    private PharmacyMultiBranchRoutingService $routingService;

    private PharmacyBranch $branchJakarta;

    private PharmacyBranch $branchBandung;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingService = app(PharmacyMultiBranchRoutingService::class);

        $this->branchJakarta = PharmacyBranch::create([
            'branch_code' => 'BR-JKT',
            'name' => 'Apotek Jkt Barat',
            'type' => 'INTERNAL',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->branchBandung = PharmacyBranch::create([
            'branch_code' => 'BR-BDG',
            'name' => 'Apotek Bdg Dago',
            'type' => 'INTERNAL',
            'city' => 'Bandung',
            'is_active' => true,
        ]);
    }

    public function test_routing_selects_local_branch_when_stock_is_sufficient(): void
    {
        // Stock available in both Jakarta and Bandung
        PharmacyBranchLot::create([
            'branch_id' => $this->branchJakarta->id,
            'drug_code' => 'ATOR-20',
            'drug_name' => 'Atorvastatin 20mg',
            'lot_number' => 'LOT-JKT-01',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        PharmacyBranchLot::create([
            'branch_id' => $this->branchBandung->id,
            'drug_code' => 'ATOR-20',
            'drug_name' => 'Atorvastatin 20mg',
            'lot_number' => 'LOT-BDG-01',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $bestBranch = $this->routingService->findBestBranch(
            patientCity: 'Jakarta',
            drugCode: 'ATOR-20',
            quantity: 30
        );

        $this->assertNotNull($bestBranch);
        $this->assertSame('BR-JKT', $bestBranch->branch_code);
    }

    public function test_routing_falls_back_to_regional_branch_when_local_is_exhausted(): void
    {
        // Jakarta has 0 stock, Bandung has 50 stock
        PharmacyBranchLot::create([
            'branch_id' => $this->branchBandung->id,
            'drug_code' => 'ORAL-ANTIVIRAL',
            'drug_name' => 'Paxlovid',
            'lot_number' => 'LOT-PAX-01',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'is_quarantined' => false,
        ]);

        $bestBranch = $this->routingService->findBestBranch(
            patientCity: 'Jakarta',
            drugCode: 'ORAL-ANTIVIRAL',
            quantity: 10
        );

        $this->assertNotNull($bestBranch);
        $this->assertSame('BR-BDG', $bestBranch->branch_code);
    }

    public function test_routing_returns_null_when_no_branch_has_sufficient_stock(): void
    {
        $bestBranch = $this->routingService->findBestBranch(
            patientCity: 'Surabaya',
            drugCode: 'RARE-ORPHAN-DRUG',
            quantity: 5
        );

        $this->assertNull($bestBranch);
    }
}
