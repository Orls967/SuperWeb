<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\Circular\Domain\Models\CirAuctionRound;
use Modules\Circular\Domain\Models\CirBid;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Ledger\CircularAccounts;
use Modules\Circular\Ledger\CircularSubjectAccounts;
use Modules\Circular\Ledger\CircularTransactionTypes;
use Modules\Logistics\Domain\Models\Location;
use Modules\Manufacturing\Domain\Models\Material;

class CircularMarketplaceT1Seeder extends Seeder
{
    private const TOTAL_LISTINGS = 1000;

    private const TOTAL_BIDS = 5000;

    private const TOTAL_ORDERS = 300;

    public function run(): void
    {
        $now = Carbon::now();

        // 1. Seed bagan akun standar jika belum
        foreach (CircularAccounts::definitions() as $def) {
            LedgerAccount::firstOrCreate(
                ['code' => $def['code'], 'asset_code' => $def['asset_code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $def['name'],
                    'kind' => $def['kind']->value,
                    'allow_negative' => $def['allow_negative'],
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }

        // 2. User penjual dan pembeli
        $seller = User::firstOrCreate(
            ['email' => 't1-seller@circular.test'],
            ['name' => 'T1 Circular Seller', 'password' => bcrypt('password'), 'role' => 'material_seller']
        );
        $buyer = User::firstOrCreate(
            ['email' => 't1-buyer@circular.test'],
            ['name' => 'T1 Circular Buyer', 'password' => bcrypt('password'), 'role' => 'material_buyer']
        );
        $admin = User::firstOrCreate(
            ['email' => 't1-admin@circular.test'],
            ['name' => 'T1 Circular Admin', 'password' => bcrypt('password'), 'role' => 'circular_admin']
        );

        CircularSubjectAccounts::userWallet($seller->id);
        CircularSubjectAccounts::userWallet($buyer->id);
        CircularSubjectAccounts::sellerPayable($seller->id);

        // 3. Lokasi & Material
        $originLoc = Location::firstOrCreate(
            ['code' => 'T1-CIR-ORIGIN'],
            [
                'name' => 'Hub Pengumpul Logam Karawang',
                'type' => 'hub',
                'city' => 'Karawang',
                'province' => 'Jawa Barat',
                'country' => 'ID',
                'lat_e6' => -6300000,
                'lng_e6' => 107300000,
                'is_active' => true,
            ]
        );

        $destLoc = Location::firstOrCreate(
            ['code' => 'T1-CIR-DEST'],
            [
                'name' => 'Pabrik Daur Ulang Peleburan Cikarang',
                'type' => 'warehouse',
                'city' => 'Cikarang',
                'province' => 'Jawa Barat',
                'country' => 'ID',
                'lat_e6' => -6320000,
                'lng_e6' => 107100000,
                'is_active' => true,
            ]
        );

        $material = Material::firstOrCreate(
            ['code' => 'SCRAP_FE'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Scrap Besi Industri Terpilah',
                'kind' => 'raw',
                'base_uom' => 'kg',
                'is_active' => true,
            ]
        );

        // 4. Ronde Lelang
        $round = CirAuctionRound::firstOrCreate(
            ['round_code' => 'CIR-RND-T1-001'],
            [
                'material_code' => 'SCRAP_FE',
                'grade_code' => 'A',
                'origin_location_id' => $originLoc->id,
                'destination_facility_id' => $destLoc->id,
                'status' => 'open',
                'clearing_price_per_kg_idr' => null,
                'cleared_volume_grams' => 0,
                'idempotency_key' => 't1-auc-round-001',
            ]
        );

        // 5. Bulk insert 1.000 listings
        if (CirListing::where('seller_party_id', $seller->id)->count() < self::TOTAL_LISTINGS) {
            $listingsData = [];
            for ($i = 1; $i <= self::TOTAL_LISTINGS; $i++) {
                $isAuction = ($i <= 100);
                $listingsData[] = [
                    'listing_code' => sprintf('CIR-LST-T1-%04d', $i),
                    'seller_party_id' => $seller->id,
                    'auction_round_id' => $isAuction ? $round->id : null,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'available_grams' => 10_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'mode' => $isAuction ? 'auction' : 'fixed',
                    'status' => 'active',
                    'origin_location_id' => $originLoc->id,
                    'idempotency_key' => sprintf('t1-lst-key-%04d', $i),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($listingsData) >= 250) {
                    DB::table('cir_listings')->insertOrIgnore($listingsData);
                    $listingsData = [];
                }
            }
            if ($listingsData !== []) {
                DB::table('cir_listings')->insertOrIgnore($listingsData);
            }
        }

        // 6. Bulk insert 5.000 bids
        if (CirBid::where('auction_round_id', $round->id)->count() < self::TOTAL_BIDS) {
            $bidsData = [];
            for ($b = 1; $b <= self::TOTAL_BIDS; $b++) {
                $bidsData[] = [
                    'auction_round_id' => $round->id,
                    'buyer_party_id' => $buyer->id,
                    'quantity_grams' => 200_000,
                    'max_price_per_kg_idr' => 4_100,
                    'allocated_grams' => 0,
                    'status' => 'active',
                    'idempotency_key' => sprintf('t1-bid-key-%05d', $b),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($bidsData) >= 500) {
                    DB::table('cir_bids')->insertOrIgnore($bidsData);
                    $bidsData = [];
                }
            }
            if ($bidsData !== []) {
                DB::table('cir_bids')->insertOrIgnore($bidsData);
            }
        }

        // 7. Buat 300 orders dengan integritas faktual (reverse shipment + receipt K1 + ledger)
        if (CirOrder::where('buyer_party_id', $buyer->id)->count() < self::TOTAL_ORDERS) {
            $firstListing = CirListing::where('seller_party_id', $seller->id)->firstOrFail();
            $ledgerService = app(LedgerService::class);

            for ($o = 1; $o <= self::TOTAL_ORDERS; $o++) {
                $orderNum = sprintf('CIR-ORD-T1-%04d', $o);
                $key = sprintf('t1-ord-key-%04d', $o);

                // Buat reverse shipment
                $shipmentId = DB::table('lgx_shipments')->insertGetId([
                    'tracking_number' => sprintf('REV-T1-%06d', $o),
                    'shipper_id' => $seller->id,
                    'source_party_id' => $seller->id,
                    'consignee_name' => 'Pabrik Daur Ulang Manufaktur T1',
                    'consignee_phone' => '0812345678',
                    'consignee_address' => json_encode(['city' => 'Cikarang']),
                    'origin_location_id' => $originLoc->id,
                    'destination_location_id' => $destLoc->id,
                    'destination_facility_id' => $destLoc->id,
                    'service_level' => 'regular',
                    'mode' => 'road',
                    'status' => 'received',
                    'is_reverse' => true,
                    'reverse_reason' => 'material_sekunder',
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'net_weight_grams' => 1_000_000,
                    'verified_weight_grams' => 1_000_000,
                    'total_chargeable_weight_g' => 1_000_000,
                    'booked_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Buat MaterialLot nyata di Manufaktur
                $lotId = (string) Str::uuid();
                DB::table('mfg_material_lots')->insert([
                    'id' => $lotId,
                    'material_id' => $material->id,
                    'lot_number' => sprintf('LOT-T1-%06d', $o),
                    'grade_code' => 'A',
                    'qty' => 1000,
                    'unit_cost_idr' => 4_000,
                    'produced_at' => $now->toDateString(),
                    'source_type' => 'reverse_shipment',
                    'source_ref' => (string) $shipmentId,
                    'source_reverse_shipment_id' => $shipmentId,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Buat K1 receipt
                $receiptId = DB::table('mfg_recycled_receipts')->insertGetId([
                    'reverse_shipment_id' => $shipmentId,
                    'material_lot_id' => $lotId,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'quantity_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'total_value_idr' => 4_000_000,
                    'idempotency_key' => "t1-receipt-key-{$o}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('lgx_shipments')->where('id', $shipmentId)->update(['plant_receipt_id' => $receiptId]);

                // 250 orders sudah settled, 50 orders in_transit / escrow_held
                $isSettled = ($o <= 250);
                $status = $isSettled ? 'settled' : 'in_transit';

                $orderId = DB::table('cir_orders')->insertGetId([
                    'order_number' => $orderNum,
                    'listing_id' => $firstListing->id,
                    'buyer_party_id' => $buyer->id,
                    'seller_party_id' => $seller->id,
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'ordered_grams' => 1_000_000,
                    'accepted_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'escrow_amount_idr' => 4_000_000,
                    'refund_amount_idr' => 0,
                    'status' => $status,
                    'origin_location_id' => $originLoc->id,
                    'destination_facility_id' => $destLoc->id,
                    'reverse_shipment_id' => $shipmentId,
                    'plant_receipt_id' => $receiptId,
                    'idempotency_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('cir_order_lines')->insert([
                    'order_id' => $orderId,
                    'line_type' => 'material_charge',
                    'material_code' => 'SCRAP_FE',
                    'grade_code' => 'A',
                    'quantity_grams' => 1_000_000,
                    'unit_price_per_kg_idr' => 4_000,
                    'subtotal_idr' => 4_000_000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Ledger posting:
                // 1. Escrow hold: debit buyer wallet, credit escrow:payment:IDR (4.000.000)
                $ledgerService->post(new PostingDTO(
                    type: CircularTransactionTypes::CIRCULAR_ESCROW_HOLD->value,
                    description: "T1 escrow hold {$orderNum}",
                    idempotencyKey: "t1:escrow:hold:{$orderId}",
                    entries: Posting::lines()
                        ->debit("wallet:user:{$buyer->id}:IDR", 4_000_000)
                        ->credit('escrow:payment:IDR', 4_000_000)
                        ->toEntries('IDR'),
                    referenceType: 'CIRCULAR_ORDER',
                    referenceId: (string) $orderId,
                ));

                if ($isSettled) {
                    // Settlement: gross 4.000.000, fee 250 bps = 100.000, net seller 3.900.000
                    $ledgerService->post(new PostingDTO(
                        type: CircularTransactionTypes::CIRCULAR_SELLER_SETTLEMENT->value,
                        description: "T1 settlement {$orderNum}",
                        idempotencyKey: "t1:settle:{$orderId}",
                        entries: Posting::lines()
                            ->debit('escrow:payment:IDR', 4_000_000)
                            ->credit("ap:cir:seller:{$seller->id}:IDR", 3_900_000)
                            ->credit('cir:fee_revenue:IDR', 100_000)
                            ->toEntries('IDR'),
                        referenceType: 'CIRCULAR_ORDER',
                        referenceId: (string) $orderId,
                    ));

                    DB::table('cir_settlements')->insert([
                        'order_id' => $orderId,
                        'seller_party_id' => $seller->id,
                        'gross_amount_idr' => 4_000_000,
                        'fee_bps' => 250,
                        'fee_amount_idr' => 100_000,
                        'net_seller_amount_idr' => 3_900_000,
                        'is_paid_out' => false,
                        'idempotency_key' => "t1-settle-key-{$orderId}",
                        'settled_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
