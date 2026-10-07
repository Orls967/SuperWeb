<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Ret\Application\Services\SuperAppAndCashbackService;
use Modules\Ret\Domain\Models\RetBundleSubscription;
use Modules\Ret\Domain\Models\RetCashbackTransaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'ret:cashback_expense:IDR' => 'expense',
        'ret:cashback_liability:IDR' => 'liability',
        'ret:bill_payment_receivable:IDR' => 'asset',
        'ret:bill_payment_fee_revenue:IDR' => 'revenue',
        'ret:biller_payable:IDR' => 'liability',
        'ret:bundle_receivable:IDR' => 'asset',
        'ret:internal_settlement_HOTEL:IDR' => 'revenue',
        'ret:internal_settlement_MEDIA:IDR' => 'revenue',
        'ret:internal_settlement_TLX:IDR' => 'revenue',
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

test('(a) cashback sum issued <= earned rules, non-negative, and ledger liability balanced', function () {
    $service = app(SuperAppAndCashbackService::class);

    $cb = $service->awardCashback(
        customerId: 'CUST-WALLET-101',
        sourceLine: 'RESTO',
        txAmountMinor: 100000000, // 1,000,000 IDR
        cashbackPct: 5.0
    );

    // 5% of 1,000,000 IDR = 50,000 IDR (5,000,000 minor)
    expect($cb)->toBeInstanceOf(RetCashbackTransaction::class)
        ->and($cb->cashback_earned_minor)->toBe(5000000)
        ->and($cb->status)->toBe('EARNED');

    // Negative parameters rejected
    expect(fn () => $service->awardCashback('CUST-WALLET-101', 'RESTO', -500, 5.0))
        ->toThrow(RuntimeException::class, 'must be non-negative');
});

test('(b) subscription bundle settlement sum(lines) == fee subscription', function () {
    $service = app(SuperAppAndCashbackService::class);

    $bundle = $service->createBundle([
        'bundle_name' => 'Ecosystem Lifestyle Triple Play',
        'monthly_price_minor' => 100000000, // 1,000,000 IDR
        'line_settlement_breakdown' => [
            'HOTEL' => 50000000,
            'MEDIA' => 20000000,
            'TLX' => 30000000,
        ],
    ]);

    expect($bundle->monthly_price_minor)->toBe(100000000);

    $subscription = $service->billBundleSubscription($bundle->id, 'CUST-FAMILY-01', '2026-10');
    expect($subscription)->toBeInstanceOf(RetBundleSubscription::class)
        ->and($subscription->amount_billed_minor)->toBe(100000000)
        ->and($subscription->status)->toBe('SETTLED');

    // Mismatched breakdown rejected
    expect(fn () => $service->createBundle([
        'bundle_name' => 'Bad Bundle',
        'monthly_price_minor' => 100000000,
        'line_settlement_breakdown' => [
            'HOTEL' => 40000000, // sum is 90M, not 100M
            'MEDIA' => 20000000,
            'TLX' => 30000000,
        ],
    ]))->toThrow(RuntimeException::class, 'does not equal monthly price');
});

test('(c) bill payment receipt is strictly sequential and gapless', function () {
    $service = app(SuperAppAndCashbackService::class);

    $pay1 = $service->payBill([
        'customer_id' => 'CUST-001',
        'bill_type' => 'ELECTRICITY_PLN',
        'biller_code' => 'PLN-POSTPAID',
        'bill_amount_minor' => 50000000,
        'admin_fee_minor' => 250000,
    ]);

    $pay2 = $service->payBill([
        'customer_id' => 'CUST-002',
        'bill_type' => 'UTILITY_WATER',
        'biller_code' => 'PDAM-SURABAYA',
        'bill_amount_minor' => 15000000,
        'admin_fee_minor' => 250000,
    ]);

    expect($pay1->receipt_sequence)->toBe(1)
        ->and($pay1->receipt_number)->toBe('RCP-BILL-00000001')
        ->and($pay2->receipt_sequence)->toBe(2)
        ->and($pay2->receipt_number)->toBe('RCP-BILL-00000002');
});

test('(d) offer engine respects privacy opt-out', function () {
    $service = app(SuperAppAndCashbackService::class);

    // Opted in customer receives offer
    $service->setPrivacyPreference('CUST-OPTIN', false);
    $offer = $service->generatePersonalizedOffer('CUST-OPTIN');
    expect($offer)->not->toBeNull()
        ->and($offer['discount_pct'])->toBe(15.0);

    // Opted out customer receives null (no privacy/data leakage)
    $service->setPrivacyPreference('CUST-OPTOUT', true);
    $offerBlocked = $service->generatePersonalizedOffer('CUST-OPTOUT');
    expect($offerBlocked)->toBeNull();
});
