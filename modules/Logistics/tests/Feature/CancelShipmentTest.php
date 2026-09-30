<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\CancelShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\CannotCancelPickedUpShipmentException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BankingSeeder::class);

    $this->shipper = User::factory()->create([
        'role' => 'shipper',
        'email' => 'shipper_cancel@logistics.test',
    ]);

    app(SetPinAction::class)->execute($this->shipper, '123456');
    app(TopUpAction::class)->execute($this->shipper, '1000000', 'IDR', 'test_topup_'.uniqid());

    $this->locOrigin = Location::create([
        'code' => 'HUB-BDJ-CANCEL',
        'name' => 'Hub Banjarmasin Cancel',
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
        'code' => 'HUB-BJB-CANCEL',
        'name' => 'Hub Banjarbaru Cancel',
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
        'name' => 'Tarif BDJ - BJB Cancel',
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
        'rate_per_kg_idr' => 50_000,
        'is_flat' => false,
    ]);

    $this->quoteAction = new QuoteShipmentAction(new ChargeableWeightCalculator);
    $this->bookAction = app(BookShipmentAction::class);
    $this->cancelAction = app(CancelShipmentAction::class);
});

test('pre-pickup prepaid shipment cancellation refunds wallet minus cancellation fee with balanced double-entry ledger', function () {
    // 2 kg * Rp 50.000 = Rp 100.000 base + 11% PPN = Rp 111.000 total
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

    $shipment = $this->bookAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Penerima Budi',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. A. Yani', 'city' => 'Banjarbaru'],
        pin: '123456'
    );

    $totalPaid = $shipment->total_amount_idr;
    $balanceAfterBooking = (float) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;

    // Configured fee is Rp 25.000
    config(['logistics.cancellation_fee_idr' => 25_000]);
    $expectedFee = 25_000;
    $expectedRefund = $totalPaid - $expectedFee;

    // Execute cancellation before pickup
    $cancelledShipment = $this->cancelAction->execute(
        user: $this->shipper,
        shipment: $shipment,
        reason: 'Salah alamat tujuan'
    );

    expect($cancelledShipment->status)->toBe(ShipmentStatus::Cancelled)
        ->and($cancelledShipment->cancellation_fee_idr)->toBe($expectedFee)
        ->and($cancelledShipment->cancelled_at)->not->toBeNull();

    // 1. Shipper wallet is refunded Rp (total - 25.000)
    $finalWallet = (float) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;
    expect($finalWallet)->toBe($balanceAfterBooking + $expectedRefund);

    // 2. Unearned freight liability is completely cleared back to 0
    $unearnedAcc = LedgerAccount::where('code', 'lgx:unearned_freight')->where('asset_code', 'IDR')->firstOrFail();
    expect((int) $unearnedAcc->cached_balance)->toBe(0);

    // 3. Freight revenue received Rp 25.000 cancellation fee
    $revenueAcc = LedgerAccount::where('code', 'lgx:freight_revenue')->where('asset_code', 'IDR')->firstOrFail();
    expect((int) $revenueAcc->cached_balance)->toBe($expectedFee);

    // 4. Double-entry ledger reconciliation passes with zero discrepancy
    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('cancellation is strictly rejected after shipment is picked up', function () {
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

    $shipment = $this->bookAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Penerima Budi',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. A. Yani', 'city' => 'Banjarbaru'],
        pin: '123456'
    );

    // Transition shipment to PickedUp
    $shipment->transitionTo(ShipmentStatus::PickedUp);

    // Attempting cancellation should throw CannotCancelPickedUpShipmentException
    expect(function () use ($shipment) {
        $this->cancelAction->execute(
            user: $this->shipper,
            shipment: $shipment,
            reason: 'Mau batal saja'
        );
    })->toThrow(CannotCancelPickedUpShipmentException::class);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::PickedUp);
});

test('unauthorized user cannot cancel another shippers shipment', function () {
    $otherUser = User::factory()->create([
        'role' => 'shipper',
        'email' => 'other_user@logistics.test',
    ]);

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

    $shipment = $this->bookAction->execute(
        shipper: $this->shipper,
        quote: $quote,
        consigneeName: 'Penerima Budi',
        consigneePhone: '081234567890',
        consigneeAddress: ['street' => 'Jl. A. Yani', 'city' => 'Banjarbaru'],
        pin: '123456'
    );

    expect(function () use ($otherUser, $shipment) {
        $this->cancelAction->execute(
            user: $otherUser,
            shipment: $shipment
        );
    })->toThrow(DomainException::class);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Booked);
});
