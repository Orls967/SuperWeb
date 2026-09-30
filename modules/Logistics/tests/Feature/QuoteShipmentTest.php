<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\NoRateCardFoundException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Surcharge;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->shipper = User::factory()->create([
        'role' => 'shipper',
        'email' => 'shipper_test@logistics.test',
    ]);

    $this->locOrigin = Location::create([
        'code' => 'HUB-BDJ-TEST',
        'name' => 'Hub Banjarmasin Test',
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
        'code' => 'HUB-BJB-TEST',
        'name' => 'Hub Banjarbaru Test',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114840000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    // Active Rate Card
    $this->rateCard = RateCard::create([
        'name' => 'Tarif BDJ - BJB Reguler',
        'origin_location_id' => $this->locOrigin->id,
        'destination_location_id' => $this->locDest->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 15_000,
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

    // Active Surcharges
    Surcharge::create([
        'code' => Surcharge::CODE_FUEL,
        'name' => 'Fuel Surcharge 5%',
        'type' => 'percentage',
        'rate' => 0.0500,
        'is_active' => true,
    ]);

    Surcharge::create([
        'code' => Surcharge::CODE_INSURANCE,
        'name' => 'Asuransi Kargo',
        'type' => 'percentage',
        'rate' => 0.0020,
        'min_amount_idr' => 10_000,
        'is_active' => true,
    ]);

    $this->action = new QuoteShipmentAction(new ChargeableWeightCalculator);
});

test('quote shipment action generates valid quote with 15-minute timelock and tamper-proof hash', function () {
    // 2 kg actual weight, 10x10x10 cm
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->action->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages,
        declaredValueIdr: 5_000_000,
        insured: true
    );

    // Chargeable weight = 2.0 kg
    // Base freight = 2 kg * Rp 10.000 = Rp 20.000 (> min charge 15.000)
    expect($quote->base_freight_idr)->toBe(20_000);

    // Fuel surcharge: 5% of 20.000 = 1.000
    // Insurance surcharge: 0.2% of 5.000.000 = 10.000 (equal to min 10.000)
    // Total surcharges: 1.000 + 10.000 = 11.000
    expect($quote->total_surcharges_idr)->toBe(11_000);

    // Subtotal: 20.000 + 11.000 = 31.000
    expect($quote->subtotal_idr)->toBe(31_000);

    // VAT 11%: 31.000 * 0.11 = 3.410
    expect($quote->vat_amount_idr)->toBe(3410);

    // Total: 31.000 + 3.410 = 34.410
    expect($quote->total_amount_idr)->toBe(34_410);

    // Timelock: expires in ~15 minutes
    expect($quote->isExpired())->toBeFalse()
        ->and($quote->secondsRemaining())->toBeGreaterThan(800)
        ->and($quote->secondsRemaining())->toBeLessThanOrEqual(900);

    // Hash integrity
    expect($quote->verifyHash())->toBeTrue()
        ->and($quote->canBeBooked())->toBeTrue();
});

test('tampered quote fails hash verification and cannot be booked', function () {
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->action->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    expect($quote->verifyHash())->toBeTrue();

    // Maliciously alter the price directly in database or memory
    $quote->total_amount_idr = 1; // Attempt to pay only Rp 1

    expect($quote->verifyHash())->toBeFalse()
        ->and($quote->canBeBooked())->toBeFalse();
});

test('expired quote cannot be booked after 15 minutes', function () {
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $quote = $this->action->execute(
        shipper: $this->shipper,
        originLocationId: $this->locOrigin->id,
        destinationLocationId: $this->locDest->id,
        serviceLevel: ServiceLevel::Regular,
        packages: $packages
    );

    // Fast-forward time 16 minutes
    $this->travel(16)->minutes();

    expect($quote->isExpired())->toBeTrue()
        ->and($quote->canBeBooked())->toBeFalse();
});

test('missing rate card throws NoRateCardFoundException', function () {
    // Other location with no rate card
    $locOther = Location::create([
        'code' => 'HUB-OTHER-TEST',
        'name' => 'Hub Other Test',
        'type' => LocationType::HUB,
        'city' => 'Samarinda',
        'province' => 'Kalimantan Timur',
        'country_code' => 'ID',
        'lat_e6' => -502187,
        'lng_e6' => 117153709,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    expect(function () use ($locOther) {
        $this->action->execute(
            shipper: $this->shipper,
            originLocationId: $this->locOrigin->id,
            destinationLocationId: $locOther->id,
            serviceLevel: ServiceLevel::Express
        );
    })->toThrow(NoRateCardFoundException::class);
});
