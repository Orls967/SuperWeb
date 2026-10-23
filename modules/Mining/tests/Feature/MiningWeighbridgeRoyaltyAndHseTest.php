<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Mining\Application\Services\MiningComplianceAndRoyaltyService;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Tests\TestCase;

class MiningWeighbridgeRoyaltyAndHseTest extends TestCase
{
    use RefreshDatabase;

    protected MiningComplianceAndRoyaltyService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningComplianceAndRoyaltyService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'MINE-WEDA-BAY',
            'name' => 'Weda Bay Nickel Complex',
            'commodity' => 'NICKEL',
            'location' => 'Central Halmahera',
        ]);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_weighbridge_ticket_hash_chain(): void
    {
        $ticket1 = $this->service->recordWeighbridgeTicket(
            siteId: $this->site->id,
            truckPlateNumber: 'DT-8891-WEDA',
            grossWeightTon: 450.00,
            tareWeightTon: 150.00,
            nickelGradePercentage: 1.85,
            destinationStockpile: 'ROM_STOCKPILE_A'
        );

        $this->assertEquals(300.00, (float) $ticket1->net_weight_ton);
        $this->assertEquals('GENESIS_MINING_HASH', $ticket1->previous_hash);
        $this->assertNotEmpty($ticket1->hash);

        // Ticket 2 chains to ticket 1
        $ticket2 = $this->service->recordWeighbridgeTicket(
            siteId: $this->site->id,
            truckPlateNumber: 'DT-8892-WEDA',
            grossWeightTon: 420.00,
            tareWeightTon: 150.00,
            nickelGradePercentage: 1.90,
            destinationStockpile: 'ROM_STOCKPILE_A'
        );

        $this->assertEquals($ticket1->hash, $ticket2->previous_hash);
    }

    public function test_royalty_calculation_and_ledger_accrual(): void
    {
        // 50,000 ton * 1,000,000 IDR/ton * 10% royalty = 5,000,000,000 IDR
        $calc = $this->service->calculateAndPostRoyalty(
            siteId: $this->site->id,
            period: '2026-10',
            totalProductionTon: 50000.00,
            benchmarkPriceIdr: 1000000,
            royaltyRatePercentage: 10.00
        );

        $this->assertEquals(5000000000, $calc->royalty_due_idr);
        $this->assertEquals('accrued', $calc->status);
    }

    public function test_hse_work_permit_verification(): void
    {
        $activePermit = MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'permit_number' => 'WP-BLAST-001',
            'permit_type' => 'BLASTING',
            'supervisor_name' => 'KTT Joko',
            'valid_until' => now()->addHours(4),
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->verifyWorkPermit($activePermit->id));

        $expiredPermit = MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'permit_number' => 'WP-BLAST-002',
            'permit_type' => 'BLASTING',
            'supervisor_name' => 'KTT Joko',
            'valid_until' => now()->subHour(),
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->verifyWorkPermit($expiredPermit->id));
        $this->assertEquals('expired', $expiredPermit->fresh()->status);
    }

    public function test_a_weighbridge_ore_reconciled_with_stockpile_and_shipment(): void
    {
        // Case 1: 50,000 ton weighbridge vs 49,500 ton shipment (1.0% variance <= 2.0% tolerance) -> reconciled
        $res1 = $this->service->reconcileWeighbridgeStockpileAndShipment(
            weighbridgeTotalTon: 50000.00,
            stockpileTons: 50000.00,
            shipmentTons: 49500.00,
            tolerancePercent: 2.0
        );
        $this->assertEquals('reconciled_ok', $res1['status']);
        $this->assertEquals(500.00, $res1['variance_ton']);
        $this->assertEquals(1.0, $res1['variance_percent']);

        // Case 2: 50,000 ton weighbridge vs 48,000 ton shipment (4.0% variance > 2.0% tolerance) -> investigation
        $res2 = $this->service->reconcileWeighbridgeStockpileAndShipment(
            weighbridgeTotalTon: 50000.00,
            stockpileTons: 50000.00,
            shipmentTons: 48000.00,
            tolerancePercent: 2.0
        );
        $this->assertEquals('investigation_required', $res2['status']);
        $this->assertEquals(2000.00, $res2['variance_ton']);
        $this->assertEquals(4.0, $res2['variance_percent']);
    }

    public function test_c_assay_bias_outside_tolerance_triggers_investigation(): void
    {
        // Normal bias: field 1.85% vs lab 1.90% (bias 0.05% <= 0.15%)
        $res1 = $this->service->verifyAssayGradeBias('SAMPLE-001', 1.85, 1.90, 0.15);
        $this->assertEquals('verified_compliant', $res1['status']);
        $this->assertFalse($res1['exceeds_tolerance']);

        // Excessive bias: field 1.85% vs lab 2.10% (bias 0.25% > 0.15%) -> triggers investigation
        $res2 = $this->service->verifyAssayGradeBias('SAMPLE-002', 1.85, 2.10, 0.15);
        $this->assertEquals('investigation_required', $res2['status']);
        $this->assertTrue($res2['exceeds_tolerance']);
    }

    public function test_e_mining_audit_zero_discrepancy(): void
    {
        $exitMiningAudit = Artisan::call('mining:audit');
        $this->assertSame(0, $exitMiningAudit);
        $this->assertStringContainsString('Mining audit PASSED cleanly', Artisan::output());

        $exitMinAudit = Artisan::call('min:audit');
        $this->assertSame(0, $exitMinAudit);
        $this->assertStringContainsString('Mining audit PASSED cleanly', Artisan::output());
    }
}
