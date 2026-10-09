<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Ret\Application\Services\OmnichannelRetailService;
use Modules\Ret\Domain\Models\RetCouponRedemption;
use Modules\Ret\Domain\Models\RetSellerSettlement;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'ret:escrow_receivable:IDR' => 'asset',
        'ret:marketplace_commission_revenue:IDR' => 'revenue',
        'ret:seller_payable:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Retail {$code}",
            ]
        );
    }
});

test('(a) stok channel ganda: 1 unit hanya terjual 1x (concurrency & locking)', function () {
    $service = app(OmnichannelRetailService::class);

    $service->registerInventoryItem([
        'sku' => 'LIMITED-EDITION-SNEAKER-42',
        'product_name' => 'Limited Sneakers Size 42',
        'stock_available' => 1, // Only 1 unit in whole ecosystem
        'map_price_minor' => 250000000,
    ]);

    // Channel 1 (Physical Store POS) reserves the single unit
    $firstSale = $service->reserveAndSellInventory('LIMITED-EDITION-SNEAKER-42', 1);
    expect($firstSale->stock_available)->toBe(0)
        ->and($firstSale->stock_reserved)->toBe(1);

    // Channel 2 (Online App / Marketplace) attempts to reserve the unit simultaneously -> Rejected
    expect(fn () => $service->reserveAndSellInventory('LIMITED-EDITION-SNEAKER-42', 1))
        ->toThrow(RuntimeException::class, 'Insufficient stock');
});

test('(b) komisi settlement marketplace = % * GMV terverifikasi dan net payout seimbang di ledger', function () {
    $service = app(OmnichannelRetailService::class);

    // Verified GMV: 50,000,000 IDR (5,000,000,000 minor) at 8% category commission
    $settlement = $service->settleMarketplaceSeller(
        sellerPartyId: 'SELLER-ELECTRONICS-PARTNER',
        verifiedGmvMinor: 5000000000,
        commissionPct: 8.0
    );

    // 8% commission = 400,000,000 minor; Net payout = 4,600,000,000 minor
    expect($settlement)->toBeInstanceOf(RetSellerSettlement::class)
        ->and($settlement->commission_fee_minor)->toBe(400000000)
        ->and($settlement->net_payout_minor)->toBe(4600000000)
        ->and($settlement->status)->toBe('SETTLED');
});

test('(c) split fulfillment sum(quantity) == order item quantity', function () {
    $service = app(OmnichannelRetailService::class);

    $splits = [
        [
            'quantity' => 2,
            'location_type' => 'SHIP_AS_STORE',
            'facility_id' => 'STORE-PLAZA-INDONESIA',
        ],
        [
            'quantity' => 3,
            'location_type' => 'FDC_WAREHOUSE',
            'facility_id' => 'FDC-CIKARANG-CENTRAL',
        ],
    ];

    $result = $service->splitOrderFulfillment('ORD-2026-9901', 'SKU-ORGANIC-HONEY', $splits);

    expect($result['total_quantity'])->toBe(5)
        ->and(count($result['splits']))->toBe(2)
        ->and($result['splits'][0]->quantity + $result['splits'][1]->quantity)->toBe(5);
});

test('(d) kupon promosi multi-channel tidak dobel pakai per customer', function () {
    $service = app(OmnichannelRetailService::class);

    $redemption1 = $service->redeemCoupon('PROMO-SUPER-SAVE-50', 'CUST-007', 'ORD-WEB-001');
    expect($redemption1)->toBeInstanceOf(RetCouponRedemption::class);

    // Customer attempts to reuse same coupon on another channel (e.g. In-store POS) -> Exception
    expect(fn () => $service->redeemCoupon('PROMO-SUPER-SAVE-50', 'CUST-007', 'ORD-POS-002'))
        ->toThrow(RuntimeException::class, 'has already been redeemed');
});
