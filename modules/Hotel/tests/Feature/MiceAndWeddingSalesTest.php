<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hotel\Application\Services\MiceAndWeddingSalesService;
use Modules\Hotel\Domain\Models\HotelProperty;
use RuntimeException;
use Tests\TestCase;

class MiceAndWeddingSalesTest extends TestCase
{
    use RefreshDatabase;

    protected MiceAndWeddingSalesService $service;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiceAndWeddingSalesService::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-MICE-01',
            'name' => 'The Royal Cliff Grand Ballroom & Resort',
            'property_type' => 'RESORT',
            'city' => 'Nusa Dua',
            'total_rooms' => 300,
        ]);

        // Register MICE ledger accounts
        $accounts = [
            'htl:wedding_receivable:IDR' => 'asset',
            'htl:wedding_revenue:IDR' => 'revenue',
            'htl:banquet_production_cost:IDR' => 'expense',
            'htl:banquet_vendor_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hotel {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_114_2_and_114_6_a_room_block_release_at_h_minus_30(): void
    {
        $contract = $this->service->createMiceContract(
            property: $this->property,
            eventType: 'CONFERENCE',
            clientName: 'ASEAN Tech Summit 2026',
            eventDate: '2026-12-15',
            totalContractValueIdr: 1_200_000_000
        );

        // Allot 100 rooms with check-in on 2026-12-15 (Cutoff H-30 = 2026-11-15)
        $block = $this->service->createRoomBlock($contract, '2026-12-15', 100, 30);
        $this->assertEquals('2026-11-15', $block->release_deadline_date);

        // Before cutoff (e.g. 2026-11-01): 0 released
        $releasedEarly = $this->service->releaseUnconfirmedRooms($block, '2026-11-01');
        $this->assertEquals(0, $releasedEarly);

        // On or after cutoff (e.g. 2026-11-16): all 100 unconfirmed rooms released back to general pool!
        $released = $this->service->releaseUnconfirmedRooms($block, '2026-11-16');
        $this->assertEquals(100, $released);
        $this->assertEquals('RELEASED', $block->refresh()->status);
    }

    public function test_114_3_and_114_6_b_banquet_production_sheet_and_margin(): void
    {
        $contract = $this->service->createMiceContract(
            property: $this->property,
            eventType: 'CORPORATE_RETREAT',
            clientName: 'Bank Megapolitan Executive Gala',
            eventDate: '2026-11-20',
            totalContractValueIdr: 500_000_000
        );

        // F&B 120m + AV 50m + Decor 30m = 200,000,000 IDR Total actual cost
        $sheet = $this->service->recordBanquetProduction(
            contract: $contract,
            menuPackage: 'Royal Nusantara 5-Course Banquet',
            paxCount: 350,
            fnbCostIdr: 120_000_000,
            avCostIdr: 50_000_000,
            decorCostIdr: 30_000_000
        );

        $this->assertEquals(200_000_000, $sheet->total_production_cost_idr);
        $this->assertEquals(200_000_000, $contract->refresh()->actual_banquet_cost_idr);

        // Margin event = 500m - 200m = 300m
        $marginIdr = $contract->contract_total_value_idr - $contract->actual_banquet_cost_idr;
        $this->assertEquals(300_000_000, $marginIdr);

        $costAcct = LedgerAccount::where('code', 'htl:banquet_production_cost:IDR')->first();
        $this->assertEquals('200000000', (string) $costAcct->cached_balance);
    }

    public function test_114_4_and_114_6_d_wedding_sequential_payment_milestones(): void
    {
        $wedding = $this->service->createMiceContract(
            property: $this->property,
            eventType: 'WEDDING',
            clientName: 'Alexander & Clarissa Oceanfront Wedding',
            eventDate: '2026-12-30',
            totalContractValueIdr: 1_000_000_000 // 1 Milyar IDR
        );

        // 1. Milestone 1: 20% (200m)
        $m1 = $this->service->advanceWeddingMilestone($wedding, 1);
        $this->assertEquals(200_000_000, $m1);
        $this->assertEquals(2, $wedding->refresh()->current_milestone_index);

        // 2. Out of order milestone attempt (e.g. jumping to 4) must fail
        try {
            $this->service->advanceWeddingMilestone($wedding, 4);
            $this->fail('Expected out of order milestone error');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Invalid wedding payment milestone', $e->getMessage());
        }

        // 3. Milestone 2: 30% (300m)
        $m2 = $this->service->advanceWeddingMilestone($wedding, 2);
        $this->assertEquals(300_000_000, $m2);

        // 4. Milestone 3: 40% (400m)
        $m3 = $this->service->advanceWeddingMilestone($wedding, 3);
        $this->assertEquals(400_000_000, $m3);

        // 5. Milestone 4: 10% (100m)
        $m4 = $this->service->advanceWeddingMilestone($wedding, 4);
        $this->assertEquals(100_000_000, $m4);
        $this->assertEquals('COMPLETED', $wedding->refresh()->status);

        // Total recognized revenue = 200m + 300m + 400m + 100m = 1,000,000,000 IDR
        $rev = LedgerAccount::where('code', 'htl:wedding_revenue:IDR')->first();
        $this->assertEquals('-1000000000', (string) $rev->cached_balance);
    }
}
