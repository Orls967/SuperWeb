<?php

namespace Modules\Venue\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Venue\Application\Services\VenueCreatorEconomyService;
use Modules\Venue\Domain\Models\Creator;
use RuntimeException;
use Tests\TestCase;

class VenueCreatorEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected VenueCreatorEconomyService $service;

    protected Creator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VenueCreatorEconomyService::class);

        $this->creator = $this->service->registerCreator('DJ ElectroNusantara', 'MELODIC_TECHNO');

        // Register venue ledger accounts
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_115_2_and_115_6_a_and_b_streaming_royalty_hold_and_payout(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);

        // 1. Accrue stream revenue: 10,000,000 IDR -> 70% artist royalty = 7,000,000 IDR
        $royaltyIdr = $this->service->accrueStreamRevenue($contract, 10_000_000, 'test-accrue-stream-1');
        $this->assertEquals(7_000_000, $royaltyIdr);

        $escrow = LedgerAccount::where('code', 'ven:creator_held_royalty_escrow:IDR')->first();
        $this->assertEquals('7000000', (string) $escrow->cached_balance);

        // 2. Attempt payout before window passes must fail
        try {
            $this->service->releaseRoyaltyPayout($contract);
            $this->fail('Expected hold window exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Payout hold active', $e->getMessage());
        }

        // 3. Release payout after window passes
        $contract->created_at = now()->subDays(15);
        $contract->save();

        $paid = $this->service->releaseRoyaltyPayout($contract);
        $this->assertEquals(7_000_000, $paid);

        $escrow->refresh();
        $payable = LedgerAccount::where('code', 'ven:creator_payable:IDR')->first();

        $this->assertEquals('0', (string) $escrow->cached_balance);
        $this->assertEquals('7000000', (string) $payable->cached_balance);
    }

    public function test_115_5_and_115_6_d_merch_split_sum_equals_total_sale(): void
    {
        // Total merch price: 350,000 IDR, 60% artist (210,000 IDR), 40% platform (140,000 IDR)
        $sale = $this->service->recordMerchSale(
            creator: $this->creator,
            itemName: 'Festival Oversized Tour Hoodie',
            totalSalePriceIdr: 350_000,
            artistSplitPercent: 60.0
        );

        $this->assertEquals(210_000, $sale->artist_share_idr);
        $this->assertEquals(140_000, $sale->platform_share_idr);
        $this->assertEquals(350_000, $sale->artist_share_idr + $sale->platform_share_idr);

        $creatorPayable = LedgerAccount::where('code', 'ven:creator_payable:IDR')->first();
        $platformRev = LedgerAccount::where('code', 'ven:platform_merch_revenue:IDR')->first();

        $this->assertEquals('210000', (string) $creatorPayable->cached_balance);
        $this->assertEquals('140000', (string) $platformRev->cached_balance);

        $this->assertStringStartsWith('MRC-', $sale->sale_code);
        $this->assertSame(strtoupper($sale->sale_code), $sale->sale_code);
        $this->assertSame('SETTLED', $sale->status);
        $this->assertSame('Festival Oversized Tour Hoodie', $sale->item_name);
        $this->assertSame($this->creator->id, $sale->creator_id);
    }

    public function test_register_creator_and_asset_attributes(): void
    {
        $customService = new VenueCreatorEconomyService(app(LedgerService::class));
        $this->assertInstanceOf(VenueCreatorEconomyService::class, $customService);

        $creator = $this->service->registerCreator('DJ Nusantara Baru', 'PROGRESSIVE_HOUSE');
        $this->assertStringStartsWith('CRT-', $creator->creator_code);
        $this->assertSame(strtoupper($creator->creator_code), $creator->creator_code);
        $this->assertSame('DJ Nusantara Baru', $creator->stage_name);
        $this->assertSame('PROGRESSIVE_HOUSE', $creator->genre);
        $this->assertSame('ACTIVE', $creator->status);

        $asset = $this->service->registerAsset($creator, 'Sunset Anthem', 'AUDIO_MASTER', 'hash-12345-sha256');
        $this->assertStringStartsWith('AST-', $asset->asset_code);
        $this->assertSame(strtoupper($asset->asset_code), $asset->asset_code);
        $this->assertSame($creator->id, $asset->creator_id);
        $this->assertSame('Sunset Anthem', $asset->title);
        $this->assertSame('AUDIO_MASTER', $asset->media_type);
        $this->assertSame('hash-12345-sha256', $asset->content_hash);
        $this->assertSame('EXCLUSIVE', $asset->license_type);

        $contract = $this->service->createRightsContract($creator, 75.0, 30);
        $this->assertStringStartsWith('RGT-', $contract->contract_code);
        $this->assertSame(strtoupper($contract->contract_code), $contract->contract_code);
        $this->assertSame(75.0, (float) $contract->royalty_share_percent);
        $this->assertSame(30, (int) $contract->payout_hold_window_days);
        $this->assertSame('ACTIVE', $contract->status);
    }

    public function test_accrue_stream_revenue_validations_and_idempotency(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);

        // 1. Key kosong ditolak
        try {
            $this->service->accrueStreamRevenue($contract, 10_000_000, '   ');
            $this->fail('Harusnya melempar InvalidArgumentException untuk key kosong');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency key wajib diisi', $e->getMessage());
        }

        // 2. Sukses via explicit key
        $r1 = $this->service->accrueStreamRevenue($contract, 10_000_000, 'test-stream-idemp-1');
        $this->assertSame(7_000_000, $r1);
        $this->assertSame(7_000_000, (int) $contract->fresh()->accrued_royalties_idr);
        $this->assertSame(7_000_000, (int) $contract->fresh()->held_royalties_idr);

        // 3. Resubmit key yang sama (replay): tidak menambah held/accrued royalties
        $r2 = $this->service->accrueStreamRevenue($contract, 10_000_000, 'test-stream-idemp-1');
        $this->assertSame(7_000_000, $r2);
        $this->assertSame(7_000_000, (int) $contract->fresh()->accrued_royalties_idr);
        $this->assertSame(7_000_000, (int) $contract->fresh()->held_royalties_idr);

        // 4. Key via HTTP header
        request()->headers->set('Idempotency-Key', 'hdr-stream-key-2');
        $r3 = $this->service->accrueStreamRevenue($contract, 10_000_000);
        $this->assertSame(7_000_000, $r3);
        $this->assertSame(14_000_000, (int) $contract->fresh()->accrued_royalties_idr);
        request()->headers->remove('Idempotency-Key');

        // 5. Key via HTTP input
        request()->merge(['idempotency_key' => 'inp-stream-key-3']);
        $r4 = $this->service->accrueStreamRevenue($contract, 10_000_000);
        $this->assertSame(7_000_000, $r4);
        $this->assertSame(21_000_000, (int) $contract->fresh()->accrued_royalties_idr);
        request()->replace([]);
    }

    public function test_release_royalty_payout_disputed_and_zero_amount(): void
    {
        $contract = $this->service->createRightsContract($this->creator, 70.0, 14);
        $contract->update(['status' => 'DISPUTED']);

        // 1. Kontrak dalam sengketa (DISPUTED) ditolak
        try {
            $this->service->releaseRoyaltyPayout($contract);
            $this->fail('Harusnya melempar exception untuk status DISPUTED');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('under dispute', $e->getMessage());
        }

        // 2. Kontrak aktif tapi nominal held royalties 0 -> return 0
        $contract->status = 'ACTIVE';
        $contract->created_at = now()->subDays(20);
        $contract->held_royalties_idr = 0;
        $contract->save();
        $paid = $this->service->releaseRoyaltyPayout($contract);
        $this->assertSame(0, $paid);
    }
}
