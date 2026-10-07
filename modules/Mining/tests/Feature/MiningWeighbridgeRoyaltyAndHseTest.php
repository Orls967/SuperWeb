<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
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

        $accounts = [
            'expense:mining_royalty:IDR' => 'expense',
            'min:royalty_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
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
}
