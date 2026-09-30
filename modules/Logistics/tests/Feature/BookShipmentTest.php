<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\InvalidQuoteException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\Surcharge;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BankingSeeder::class);

    $this->shipper = User::factory()->create([
        'role' => 'shipper',
        'email' => 'shipper_book@logistics.test',
    ]);

    // Set PIN 123456
    app(SetPinAction::class)->execute($this->shipper, '123456');

    // Give shipper initial balance: Rp 1.000.000
    app(TopUpAction::class)->execute($this->shipper, '1000000', 'IDR', 'test_topup_'.uniqid());

    $this->locOrigin = Location::create([
        'code' => 'HUB-BDJ-BOOK',
        'name' => 'Hub Banjarmasin Book',
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
        'code' => 'HUB-BJB-BOOK',
        'name' => 'Hub Banjarbaru Book',
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
        'name' => 'Tarif BDJ - BJB Book',
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
        'max_weight_kg' => 10,
        'rate_per_kg_idr' => 10_000,
        'is_flat' => false,
    ]);

    Surcharge::create([
        'code' => Surcharge::CODE_FUEL,
        'name' => 'Fuel Surcharge 5%',
        'type' => 'percentage',
        'rate' => 0.0500,
        'is_active' => true,
    ]);

    $this->quoteAction = new QuoteShipmentAction(new ChargeableWeightCalculator);
    $this->bookAction = app(BookShipmentAction::class);
});

test('prepaid shipment booking deducts shipper wallet and credits unearned freight with balanced ledger', function () {
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100, 'description' => 'Buku Cetak'],
    ];

    $quote = $this->quoteAction->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    $initialWalletBalance = (float) $this->shipper->walletAccount('IDR')->cached_balance;
    $totalAmount = $quote->total_amount_idr;

    $shipment = $this->bookAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Budi Santoso',
        consigneePhone: '081234567890',
        consigneeAddress: [
            'street' => 'Jl. Pangeran Samudera No. 45',
            'city' => 'Banjarbaru',
            'postal_code' => '70714',
        ],
        pin: '123456'
    );

    // 1. Shipment created correctly
    expect($shipment->status)->toBe(ShipmentStatus::Booked)
        ->and($shipment->payment_terms)->toBe(PaymentTerms::Prepaid)
        ->and($shipment->booked_at)->not->toBeNull()
        ->and($shipment->total_amount_idr)->toBe($totalAmount)
        ->and(TrackingNumber::validate($shipment->tracking_number))->toBeTrue();

    // 2. Packages created
    expect($shipment->packages()->count())->toBe(1)
        ->and($shipment->packages()->first()->description)->toBe('Buku Cetak');

    // 3. Quote marked as booked
    expect($quote->fresh()->is_booked)->toBeTrue();

    // 4. Shipper wallet deducted
    $finalWalletBalance = (float) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;
    expect($finalWalletBalance)->toBe($initialWalletBalance - $totalAmount);

    // 5. Unearned freight credited
    $unearnedAcc = LedgerAccount::where('code', 'lgx:unearned_freight')->where('asset_code', 'IDR')->firstOrFail();
    expect((int) $unearnedAcc->cached_balance)->toBe($totalAmount);

    // 6. Double-entry ledger reconciliation is perfectly zero
    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('booking fails and rolls back completely when shipper has insufficient wallet balance', function () {
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $poorShipper = User::factory()->create([
        'role' => 'shipper',
        'email' => 'poor_shipper@logistics.test',
    ]);
    app(SetPinAction::class)->execute($poorShipper, '123456');
    // Balance 0

    $quote = $this->quoteAction->execute(
        shipper: $poorShipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    expect(function () use ($poorShipper, $quote) {
        $this->bookAction->execute(
            shipper: $poorShipper,
            quote: $quote,
            consigneeName: 'Penerima Budi',
            consigneePhone: '081299999999',
            consigneeAddress: ['street' => 'Jl. A. Yani', 'city' => 'Banjarbaru'],
            pin: '123456'
        );
    })->toThrow(InsufficientFundsException::class);

    // No shipment or packages created
    expect(Shipment::count())->toBe(0)
        ->and(Package::count())->toBe(0)
        ->and($quote->fresh()->is_booked)->toBeFalse();
});

test('booking fails when wallet pin is incorrect', function () {
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

    expect(function () use ($quote) {
        $this->bookAction->execute(
            shipper: $this->shipper,
            quote: $quote,
            consigneeName: 'Budi Santoso',
            consigneePhone: '081234567890',
            consigneeAddress: ['street' => 'Jl. Samudera', 'city' => 'Banjarbaru'],
            pin: '999999' // WRONG PIN
        );
    })->toThrow(InvalidPinException::class);

    expect(Shipment::count())->toBe(0)
        ->and($quote->fresh()->is_booked)->toBeFalse();
});

test('booking cannot be executed twice with the same quote', function () {
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

    $this->bookAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Budi Santoso',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. Samudera', 'city' => 'Banjarbaru'],
        pin: '123456'
    );

    expect(function () use ($quote) {
        $this->bookAction->execute(
            shipper: $this->shipper,
            quote: $quote,
            consigneeName: 'Budi Santoso',
            consigneePhone: '081234567890',
            consigneeAddress: ['street' => 'Jl. Samudera', 'city' => 'Banjarbaru'],
            pin: '123456'
        );
    })->toThrow(InvalidQuoteException::class);

    expect(Shipment::count())->toBe(1);
});
