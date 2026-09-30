<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\PayLogisticsInvoiceAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\CreditLimitExceededException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BankingSeeder::class);

    $this->shipper = User::factory()->create([
        'role' => 'shipper',
        'name' => 'PT Borneo Logistik Makmur',
        'email' => 'borneo@shipper.test',
    ]);

    app(SetPinAction::class)->execute($this->shipper, '123456');

    // Give shipper initial balance: Rp 10.000.000
    app(TopUpAction::class)->execute($this->shipper, '10000000', 'IDR', 'test_topup_'.uniqid());

    // Create Shipper Account with limit Rp 5.000.000
    $this->shipperAccount = ShipperAccount::create([
        'shipper_id' => $this->shipper->id,
        'credit_limit_idr' => 5_000_000,
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $this->locOrigin = Location::create([
        'code' => 'HUB-BDJ-POSTPAID',
        'name' => 'Hub Banjarmasin Postpaid',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3316694,
        'lng_e6' => 114590111,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->locDest = Location::create([
        'code' => 'HUB-BJB-POSTPAID',
        'name' => 'Hub Banjarbaru Postpaid',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114840000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->rateCard = RateCard::create([
        'name' => 'Tarif BDJ - BJB Postpaid',
        'origin_location_id' => $this->locOrigin->id,
        'destination_location_id' => $this->locDest->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'is_active' => true,
    ]);

    RateBracket::create([
        'rate_card_id' => $this->rateCard->id,
        'min_weight_kg' => 0,
        'max_weight_kg' => 100,
        'rate_per_kg_idr' => 10_000,
        'is_flat' => false,
    ]);

    $this->quoteAction = new QuoteShipmentAction(new ChargeableWeightCalculator);
    $this->bookPostpaidAction = app(BookPostpaidShipmentAction::class);
    $this->payInvoiceAction = app(PayLogisticsInvoiceAction::class);
});

test('shipper can book postpaid shipment within credit limit without immediate wallet deduction', function () {
    $packages = [
        ['weight_g' => 5000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    $initialWalletBalance = (float) $this->shipper->walletAccount('IDR')->cached_balance;

    $shipment = $this->bookPostpaidAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Penerima B2B',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. Industri No. 1', 'city' => 'Banjarbaru']
    );

    expect($shipment->status)->toBe(ShipmentStatus::Booked)
        ->and($shipment->payment_terms)->toBe(PaymentTerms::Postpaid)
        ->and($shipment->invoice_id)->toBeNull();

    // Wallet is NOT deducted immediately
    $currentWalletBalance = (float) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;
    expect($currentWalletBalance)->toBe($initialWalletBalance);

    // Outstanding balance reflects the postpaid shipment
    expect($this->shipperAccount->calculateOutstandingBalance())->toBe($shipment->total_amount_idr);
});

test('postpaid booking is rejected when exceeding credit limit', function () {
    // Set credit limit lower to Rp 20.000
    $this->shipperAccount->update(['credit_limit_idr' => 20_000]);

    // 5 kg shipment costs ~Rp 55.500 (with VAT) > Rp 20.000 limit
    $packages = [
        ['weight_g' => 5000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    expect(function () use ($quote) {
        $this->bookPostpaidAction->execute(
            shipper: $this->shipper,
            quote: $quote,
            consigneeName: 'Penerima B2B',
            consigneePhone: '081234567890',
            consigneeAddress: ['street' => 'Jl. Industri No. 1', 'city' => 'Banjarbaru']
        );
    })->toThrow(CreditLimitExceededException::class);

    expect(Shipment::count())->toBe(0);
});

test('monthly invoice command aggregates uninvoiced shipments and is idempotent', function () {
    // Create 2 postpaid shipments
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote1 = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );
    $shipment1 = $this->bookPostpaidAction->execute(
        shipper: $this->shipper,
        quote: $quote1,
        consigneeName: 'Penerima A',
        consigneePhone: '0811111111',
        consigneeAddress: ['street' => 'Jl. A', 'city' => 'Banjarbaru']
    );

    $quote2 = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );
    $shipment2 = $this->bookPostpaidAction->execute(
        shipper: $this->shipper,
        quote: $quote2,
        consigneeName: 'Penerima B',
        consigneePhone: '0822222222',
        consigneeAddress: ['street' => 'Jl. B', 'city' => 'Banjarbaru']
    );

    $expectedTotal = $shipment1->total_amount_idr + $shipment2->total_amount_idr;

    // Run command for period 2026-09
    $this->artisan('lgx:invoice-shippers', ['period' => '2026-09'])
        ->assertSuccessful();

    // 1 invoice created
    expect(LogisticsInvoice::count())->toBe(1);
    $invoice = LogisticsInvoice::first();
    expect($invoice->billing_period)->toBe('2026-09')
        ->and($invoice->total_amount_idr)->toBe($expectedTotal)
        ->and($invoice->status)->toBe('unpaid')
        ->and($shipment1->fresh()->invoice_id)->toBe($invoice->id)
        ->and($shipment2->fresh()->invoice_id)->toBe($invoice->id);

    // Run command AGAIN -> Idempotent, no duplicates created
    $this->artisan('lgx:invoice-shippers', ['period' => '2026-09'])
        ->assertSuccessful();

    expect(LogisticsInvoice::count())->toBe(1);
});

test('paying logistics invoice debits shipper wallet and credits AR account with zero reconcile discrepancy', function () {
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    $this->bookPostpaidAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Penerima B2B',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. A', 'city' => 'Banjarbaru']
    );

    $this->artisan('lgx:invoice-shippers', ['period' => '2026-09'])->assertSuccessful();
    $invoice = LogisticsInvoice::firstOrFail();

    $initialWallet = (float) $this->shipper->walletAccount('IDR')->cached_balance;

    // Pay invoice with valid PIN
    $paidInvoice = $this->payInvoiceAction->execute(
        shipper: $this->shipper,
        invoice: $invoice,
        pin: '123456'
    );

    expect($paidInvoice->status)->toBe('paid')
        ->and($paidInvoice->paid_amount_idr)->toBe($invoice->total_amount_idr)
        ->and($paidInvoice->paid_at)->not->toBeNull();

    // Wallet deducted
    $finalWallet = (float) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;
    expect($finalWallet)->toBe($initialWallet - $invoice->total_amount_idr);

    // AR account credited
    $arAcc = LedgerAccount::where('code', "lgx:ar:{$this->shipper->id}")->where('asset_code', 'IDR')->firstOrFail();
    expect((int) $arAcc->cached_balance)->toBe($invoice->total_amount_idr);

    // Reconcile is clean
    $this->artisan('bank:reconcile')->assertSuccessful();
});
