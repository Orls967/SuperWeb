<?php

declare(strict_types=1);

namespace Tests\Acceptance\Circular;

use App\Models\User;
use App\Quality\Audit\Fixtures\CirAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Circular\Application\Actions\CloseAuctionRoundAction;
use Modules\Circular\Application\Actions\CreateAuctionRoundAction;
use Modules\Circular\Application\Actions\CreateListingAction;
use Modules\Circular\Application\Actions\PlaceOrderAction;
use Modules\Circular\Application\Actions\RecordOrderPlantReceiptAction;
use Modules\Circular\Application\Actions\SettleOrderAction;
use Modules\Circular\Application\Actions\SubmitBidAction;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Ledger\CircularSubjectAccounts;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Manufacturing\Domain\Models\Material;
use Tests\TestCase;

class CircularMarketplaceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;

    protected User $buyerUser;

    protected User $otherBuyerUser;

    protected User $circularAdmin;

    protected User $customerUser;

    protected Location $sellerLocation;

    protected Location $buyerPlantLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);

        $this->sellerUser = User::factory()->create(['role' => 'material_seller']);
        $this->buyerUser = User::factory()->create(['role' => 'material_buyer']);
        $this->otherBuyerUser = User::factory()->create(['role' => 'material_buyer']);
        $this->circularAdmin = User::factory()->create(['role' => 'circular_admin']);
        $this->customerUser = User::factory()->create(['role' => 'customer']);

        $this->sellerLocation = Location::create([
            'code' => 'CIR-SELLER-LOC',
            'name' => 'Gudang Penjual Limbah Bekasi',
            'type' => 'hub',
            'city' => 'Bekasi',
            'province' => 'Jawa Barat',
            'country' => 'ID',
            'lat_e6' => -6240000,
            'lng_e6' => 107000000,
            'is_active' => true,
        ]);

        $this->buyerPlantLocation = Location::create([
            'code' => 'CIR-PLANT-LOC',
            'name' => 'Pabrik Daur Ulang Pembeli Cikarang',
            'type' => 'warehouse',
            'city' => 'Cikarang',
            'province' => 'Jawa Barat',
            'country' => 'ID',
            'lat_e6' => -6300000,
            'lng_e6' => 107150000,
            'is_active' => true,
        ]);

        Material::firstOrCreate(
            ['code' => 'SCRAP_FE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Scrap Besi Industri',
                'kind' => 'raw',
                'base_uom' => 'kg',
                'is_active' => true,
            ]
        );

        CircularSubjectAccounts::userWallet($this->sellerUser->id);
        CircularSubjectAccounts::userWallet($this->buyerUser->id);
        CircularSubjectAccounts::userWallet($this->otherBuyerUser->id);
        CircularSubjectAccounts::sellerPayable($this->sellerUser->id);
    }

    public function test_1_route_access_matrix_enforces_roles_across_all_circular_route_groups(): void
    {
        // /circular/listings -> material_seller (200), material_buyer (403), customer (403)
        $this->actingAs($this->sellerUser)->get('/circular/listings')->assertOk();
        $this->actingAs($this->buyerUser)->get('/circular/listings')->assertForbidden();
        $this->actingAs($this->customerUser)->get('/circular/listings')->assertForbidden();

        // /circular/market -> material_buyer (200), material_seller (403), customer (403)
        $this->actingAs($this->buyerUser)->get('/circular/market')->assertOk();
        $this->actingAs($this->sellerUser)->get('/circular/market')->assertForbidden();
        $this->actingAs($this->customerUser)->get('/circular/market')->assertForbidden();

        // /circular/auctions -> circular_admin (200), material_seller (403), material_buyer (403)
        $this->actingAs($this->circularAdmin)->get('/circular/auctions')->assertOk();
        $this->actingAs($this->sellerUser)->get('/circular/auctions')->assertForbidden();
        $this->actingAs($this->buyerUser)->get('/circular/auctions')->assertForbidden();
    }

    public function test_2_end_to_end_marketplace_flow_escrow_reverse_shipment_k1_receipt_settlement_and_idempotency(): void
    {
        // 1. Listing oleh penjual: 10.000.000 gram (10 ton) SCRAP_FE grade A @ Rp 4.000/kg
        $listing = app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 10_000_000,
            'unit_price_per_kg_idr' => 4_000,
            'mode' => 'fixed',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        expect($listing->available_grams)->toBe(10_000_000)
            ->and($listing->status)->toBe('active');

        // 2. Tanpa Idempotency-Key pada HTTP POST order -> 422
        $this->actingAs($this->buyerUser)
            ->postJson('/circular/orders', [
                'listing_id' => $listing->id,
                'ordered_grams' => 5_000_000,
                'destination_facility_id' => $this->buyerPlantLocation->id,
            ])
            ->assertStatus(422);

        // 3. Place Order 5.000.000 gram (5 ton) @ Rp 4.000/kg = Rp 20.000.000 dengan Idempotency-Key
        $order = app(PlaceOrderAction::class)->execute([
            'listing_id' => $listing->id,
            'buyer_party_id' => $this->buyerUser->id,
            'ordered_grams' => 5_000_000,
            'destination_facility_id' => $this->buyerPlantLocation->id,
            'idempotency_key' => 'cir-order-key-001',
        ], $this->buyerUser);

        // Idempotensi key sama menghasilkan order yang sama dan hanya 1 kali mendebit escrow
        $orderDuplicate = app(PlaceOrderAction::class)->execute([
            'listing_id' => $listing->id,
            'buyer_party_id' => $this->buyerUser->id,
            'ordered_grams' => 5_000_000,
            'destination_facility_id' => $this->buyerPlantLocation->id,
            'idempotency_key' => 'cir-order-key-001',
        ], $this->buyerUser);

        expect($orderDuplicate->id)->toBe($order->id)
            ->and($order->escrow_amount_idr)->toBe(20_000_000)
            ->and($order->status)->toBe('in_transit')
            ->and($order->reverse_shipment_id)->not->toBeNull();

        // Verifikasi reverse shipment P1.1 terbuat dengan alasan material_sekunder
        $reverseShipment = Shipment::findOrFail($order->reverse_shipment_id);
        expect($reverseShipment->is_reverse)->toBeTrue()
            ->and($reverseShipment->reverse_reason)->toBe('material_sekunder');

        // Verifikasi saldo escrow hold: wallet buyer -20.000.000, escrow:payment:IDR +20.000.000
        $buyerWallet = LedgerAccount::where('code', "wallet:user:{$this->buyerUser->id}:IDR")->firstOrFail();
        $escrowAcc = LedgerAccount::where('code', 'escrow:payment:IDR')->firstOrFail();
        expect((int) $buyerWallet->cached_balance)->toBe(-20_000_000)
            ->and((int) $escrowAcc->cached_balance)->toBe(20_000_000);

        // 4. Penerimaan di pabrik pembeli via K1 RecycledMaterialReceiver (tanpa susut: 5.000.000 gram)
        $orderAfterReceipt = app(RecordOrderPlantReceiptAction::class)->execute(
            order: $order,
            acceptedGrams: 5_000_000,
            gradeCode: 'A',
            idempotencyKey: 'cir-receipt-key-001',
            actor: $this->circularAdmin
        );

        expect($orderAfterReceipt->status)->toBe('received')
            ->and($orderAfterReceipt->accepted_grams)->toBe(5_000_000)
            ->and($orderAfterReceipt->plant_receipt_id)->not->toBeNull()
            ->and($orderAfterReceipt->refund_amount_idr)->toBe(0);

        // 5. Settlement penjual + payout ke wallet penjual (fee 250 bps = 2,5% dari 20.000.000 = 500.000; net seller = 19.500.000)
        $settlement = app(SettleOrderAction::class)->execute(
            order: $orderAfterReceipt,
            idempotencyKey: 'cir-settle-key-001',
            feeBps: 250,
            payoutImmediately: true
        );

        // Idempotensi settlement: panggil ulang dengan key sama
        $settlementDuplicate = app(SettleOrderAction::class)->execute(
            order: $orderAfterReceipt->fresh(),
            idempotencyKey: 'cir-settle-key-001',
            feeBps: 250,
            payoutImmediately: true
        );

        expect($settlementDuplicate->id)->toBe($settlement->id)
            ->and($settlement->gross_amount_idr)->toBe(20_000_000)
            ->and($settlement->fee_amount_idr)->toBe(500_000)
            ->and($settlement->net_seller_amount_idr)->toBe(19_500_000);

        $escrowAcc->refresh();
        $feeAcc = LedgerAccount::where('code', 'cir:fee_revenue:IDR')->firstOrFail();
        $sellerApAcc = LedgerAccount::where('code', "ap:cir:seller:{$this->sellerUser->id}:IDR")->firstOrFail();
        $sellerWallet = LedgerAccount::where('code', "wallet:user:{$this->sellerUser->id}:IDR")->firstOrFail();

        // Escrow habis (0), fee revenue 500.000, AP seller 0 (karena sudah di-payout), wallet seller +19.500.000 (kredit)
        expect((int) $escrowAcc->cached_balance)->toBe(0)
            ->and((int) $feeAcc->cached_balance)->toBe(500_000)
            ->and((int) $sellerApAcc->cached_balance)->toBe(0)
            ->and((int) $sellerWallet->cached_balance)->toBe(19_500_000);
    }

    public function test_3_business_rules_over_stock_422_early_settlement_rejected_and_shrinkage_refund(): void
    {
        $listing = app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 2_000_000,
            'unit_price_per_kg_idr' => 5_000,
            'mode' => 'fixed',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        // Aturan 1: Beli melebihi stok -> 422 ValidationException
        expect(fn () => app(PlaceOrderAction::class)->execute([
            'listing_id' => $listing->id,
            'buyer_party_id' => $this->buyerUser->id,
            'ordered_grams' => 2_500_000,
            'destination_facility_id' => $this->buyerPlantLocation->id,
            'idempotency_key' => 'cir-overstock-001',
        ], $this->buyerUser))->toThrow(ValidationException::class);

        // Buat order valid 2.000.000 gram (2 ton) @ Rp 5.000/kg = Rp 10.000.000
        $order = app(PlaceOrderAction::class)->execute([
            'listing_id' => $listing->id,
            'buyer_party_id' => $this->buyerUser->id,
            'ordered_grams' => 2_000_000,
            'destination_facility_id' => $this->buyerPlantLocation->id,
            'idempotency_key' => 'cir-valid-shrink-001',
        ], $this->buyerUser);

        // Aturan 2: Settlement sebelum penerimaan (status belum 'received') -> ditolak 422 ValidationException
        expect(fn () => app(SettleOrderAction::class)->execute(
            order: $order,
            idempotencyKey: 'cir-early-settle-001'
        ))->toThrow(ValidationException::class);

        // Aturan 3: Susut timbangan di pabrik -> diterima 1.800.000 gram (kurang 200.000 gram = 200 kg)
        // Refund ke pembeli = round_half_up(200.000 * 5.000 / 1.000) = Rp 1.000.000
        $orderReceived = app(RecordOrderPlantReceiptAction::class)->execute(
            order: $order,
            acceptedGrams: 1_800_000,
            gradeCode: 'A',
            idempotencyKey: 'cir-shrink-receipt-001',
            actor: $this->circularAdmin
        );

        expect($orderReceived->refund_amount_idr)->toBe(1_000_000)
            ->and($orderReceived->accepted_grams)->toBe(1_800_000);

        // Settlement dihitung dari gram diterima (1.800 kg * Rp 5.000 = Rp 9.000.000; fee 250 bps = Rp 225.000; net = Rp 8.775.000)
        $settlement = app(SettleOrderAction::class)->execute(
            order: $orderReceived,
            idempotencyKey: 'cir-shrink-settle-001',
            feeBps: 250,
            payoutImmediately: false
        );

        expect($settlement->gross_amount_idr)->toBe(9_000_000)
            ->and($settlement->fee_amount_idr)->toBe(225_000)
            ->and($settlement->net_seller_amount_idr)->toBe(8_775_000);

        $escrowAcc = LedgerAccount::where('code', 'escrow:payment:IDR')->firstOrFail();
        expect((int) $escrowAcc->cached_balance)->toBe(0);
    }

    public function test_4_idor_protection_on_sealed_bids_and_private_orders(): void
    {
        $round = app(CreateAuctionRoundAction::class)->execute([
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'origin_location_id' => $this->sellerLocation->id,
            'destination_facility_id' => $this->buyerPlantLocation->id,
        ], $this->circularAdmin);

        $bidBuyer1 = app(SubmitBidAction::class)->execute([
            'auction_round_id' => $round->id,
            'buyer_party_id' => $this->buyerUser->id,
            'quantity_grams' => 3_000_000,
            'max_price_per_kg_idr' => 4_200,
            'idempotency_key' => 'bid-buyer-1',
        ], $this->buyerUser);

        // Buyer 2 tidak boleh melihat detail bid tertutup milik Buyer 1 -> 403
        $this->actingAs($this->otherBuyerUser)
            ->get("/circular/bids/{$bidBuyer1->id}")
            ->assertForbidden();

        // Buyer 1 sendiri boleh melihat bid miliknya -> 200
        $this->actingAs($this->buyerUser)
            ->get("/circular/bids/{$bidBuyer1->id}")
            ->assertOk();

        // Buat order milik Buyer 1
        $listing = app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 5_000_000,
            'unit_price_per_kg_idr' => 4_000,
            'mode' => 'fixed',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        $orderBuyer1 = app(PlaceOrderAction::class)->execute([
            'listing_id' => $listing->id,
            'buyer_party_id' => $this->buyerUser->id,
            'ordered_grams' => 1_000_000,
            'destination_facility_id' => $this->buyerPlantLocation->id,
            'idempotency_key' => 'order-idor-1',
        ], $this->buyerUser);

        // Buyer 2 tidak boleh mengakses order milik Buyer 1 -> 403
        $this->actingAs($this->otherBuyerUser)
            ->get("/circular/orders/{$orderBuyer1->id}")
            ->assertForbidden();
    }

    public function test_5_circular_audit_passes_clean_and_detects_corruption_fixture(): void
    {
        $exitCodeClean = Artisan::call('cir:audit');
        expect($exitCodeClean)->toBe(0);

        $fixture = new CirAuditCorruptionFixture;
        $keyword = $fixture->corrupt();

        $this->artisan('cir:audit')
            ->assertFailed()
            ->expectsOutputToContain($keyword);
    }

    public function test_6_call_auction_round_clearing_partial_fill_and_idempotent_double_close(): void
    {
        $round = app(CreateAuctionRoundAction::class)->execute([
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'origin_location_id' => $this->sellerLocation->id,
            'destination_facility_id' => $this->buyerPlantLocation->id,
        ], $this->circularAdmin);

        // Ask (Listing mode auction): S1 12 ton @ 1.100, S2 10 ton @ 1.300, S3 10 ton @ 1.450 (D24-3)
        app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'auction_round_id' => $round->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 12_000_000,
            'unit_price_per_kg_idr' => 1_100,
            'mode' => 'auction',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'auction_round_id' => $round->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 10_000_000,
            'unit_price_per_kg_idr' => 1_300,
            'mode' => 'auction',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        app(CreateListingAction::class)->execute([
            'seller_party_id' => $this->sellerUser->id,
            'auction_round_id' => $round->id,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 10_000_000,
            'unit_price_per_kg_idr' => 1_450,
            'mode' => 'auction',
            'origin_location_id' => $this->sellerLocation->id,
        ], $this->sellerUser);

        // Bids: B1 10 ton @ 1.500, B2 15 ton @ 1.400, B3 10 ton @ 1.200
        $bid1 = app(SubmitBidAction::class)->execute([
            'auction_round_id' => $round->id,
            'buyer_party_id' => $this->buyerUser->id,
            'quantity_grams' => 10_000_000,
            'max_price_per_kg_idr' => 1_500,
            'idempotency_key' => 'auc-bid-1',
        ], $this->buyerUser);

        $bid2 = app(SubmitBidAction::class)->execute([
            'auction_round_id' => $round->id,
            'buyer_party_id' => $this->otherBuyerUser->id,
            'quantity_grams' => 15_000_000,
            'max_price_per_kg_idr' => 1_400,
            'idempotency_key' => 'auc-bid-2',
        ], $this->otherBuyerUser);

        $bid3 = app(SubmitBidAction::class)->execute([
            'auction_round_id' => $round->id,
            'buyer_party_id' => $this->buyerUser->id,
            'quantity_grams' => 10_000_000,
            'max_price_per_kg_idr' => 1_200,
            'idempotency_key' => 'auc-bid-3',
        ], $this->buyerUser);

        // Tutup ronde pertama kali
        $closedRound1 = app(CloseAuctionRoundAction::class)->execute($round, 'close-round-key-1', $this->circularAdmin);

        // Tutup ronde kedua kali (idempoten) -> hasil identik, order tidak terduplikasi
        $closedRound2 = app(CloseAuctionRoundAction::class)->execute($round->fresh(), 'close-round-key-1', $this->circularAdmin);

        expect($closedRound1->status)->toBe('closed')
            ->and($closedRound1->clearing_price_per_kg_idr)->toBe(1_350)
            ->and($closedRound1->cleared_volume_grams)->toBe(22_000_000)
            ->and($closedRound2->clearing_price_per_kg_idr)->toBe(1_350)
            ->and(CirOrder::where('auction_round_id', $round->id)->count())->toBe(3);

        $bid1->refresh();
        $bid2->refresh();
        $bid3->refresh();

        expect($bid1->allocated_grams)->toBe(10_000_000)
            ->and($bid2->allocated_grams)->toBe(12_000_000)
            ->and($bid3->allocated_grams)->toBe(0);
    }
}
