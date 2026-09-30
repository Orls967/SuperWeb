<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\OverlappingRateCardException;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Surcharge;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->locA = Location::create([
        'code' => 'TEST_LOC_A',
        'name' => 'Lokasi Test A',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3316694,
        'lng_e6' => 114590111,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->locB = Location::create([
        'code' => 'TEST_LOC_B',
        'name' => 'Lokasi Test B',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114840000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);
});

test('rate card calculates cost based on matching weight bracket', function () {
    $card = RateCard::create([
        'name' => 'Tarif Banjarmasin - Banjarbaru Reguler',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'is_active' => true,
    ]);

    // Bracket 0 - 5 kg: Rp 12.000 / kg
    $b1 = RateBracket::create([
        'rate_card_id' => $card->id,
        'min_weight_kg' => 0,
        'max_weight_kg' => 5,
        'rate_per_kg_idr' => 12_000,
        'is_flat' => false,
    ]);

    // Bracket > 5 kg: Rp 10.000 / kg
    $b2 = RateBracket::create([
        'rate_card_id' => $card->id,
        'min_weight_kg' => 5.01,
        'max_weight_kg' => null,
        'rate_per_kg_idr' => 10_000,
        'is_flat' => false,
    ]);

    $card->load('brackets');

    $bracketFor3Kg = $card->findBracketForWeight(3.0);
    expect($bracketFor3Kg->id)->toBe($b1->id)
        ->and($bracketFor3Kg->calculateCost(BigDecimal::of(3))->toInt())->toBe(36_000);

    $bracketFor10Kg = $card->findBracketForWeight(10.0);
    expect($bracketFor10Kg->id)->toBe($b2->id)
        ->and($bracketFor10Kg->calculateCost(BigDecimal::of(10))->toInt())->toBe(100_000);
});

test('overlapping active rate cards on same lane key are rejected with exception', function () {
    RateCard::create([
        'name' => 'Tarif Periode 1',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-06-30',
        'is_active' => true,
    ]);

    // Second card overlapping (starts 2026-05-01)
    expect(function () {
        RateCard::create([
            'name' => 'Tarif Periode Bentrok',
            'origin_location_id' => $this->locA->id,
            'destination_location_id' => $this->locB->id,
            'service_level' => ServiceLevel::Regular,
            'mode' => TransportMode::ROAD,
            'min_charge_idr' => 10_000,
            'valid_from' => '2026-05-01',
            'valid_to' => '2026-12-31',
            'is_active' => true,
        ]);
    })->toThrow(OverlappingRateCardException::class);
});

test('non-overlapping rate cards are allowed', function () {
    // Card 1: Jan - Jun 2026
    $card1 = RateCard::create([
        'name' => 'Tarif Semester 1',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-06-30',
        'is_active' => true,
    ]);

    // Card 2: Jul - Dec 2026 (No overlap)
    $card2 = RateCard::create([
        'name' => 'Tarif Semester 2',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-12-31',
        'is_active' => true,
    ]);

    // Card 3: Same date as Card 1 but different ServiceLevel (Express) (No overlap)
    $card3 = RateCard::create([
        'name' => 'Tarif Express Semester 1',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Express,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 15_000,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-06-30',
        'is_active' => true,
    ]);

    expect($card1->exists)->toBeTrue()
        ->and($card2->exists)->toBeTrue()
        ->and($card3->exists)->toBeTrue();
});

test('surcharges calculate correctly for percentage, min fee, and flat types', function () {
    // Fuel surcharge: 5% of base freight
    $fuel = Surcharge::create([
        'code' => Surcharge::CODE_FUEL,
        'name' => 'Fuel Surcharge 5%',
        'type' => 'percentage',
        'rate' => 0.0500,
        'is_active' => true,
    ]);
    expect($fuel->calculate(BigDecimal::of(100_000))->toInt())->toBe(5_000);

    // Insurance: 0.2% of declared value, min Rp 10.000
    $insurance = Surcharge::create([
        'code' => Surcharge::CODE_INSURANCE,
        'name' => 'Asuransi Kargo',
        'type' => 'percentage',
        'rate' => 0.0020,
        'min_amount_idr' => 10_000,
        'is_active' => true,
    ]);
    // Declared value 1.000.000 -> 0.2% = 2.000 < min 10.000 -> returns 10.000
    expect($insurance->calculate(BigDecimal::of(50_000), ['declared_value_idr' => 1_000_000])->toInt())->toBe(10_000);
    // Declared value 10.000.000 -> 0.2% = 20.000 > min 10.000 -> returns 20.000
    expect($insurance->calculate(BigDecimal::of(50_000), ['declared_value_idr' => 10_000_000])->toInt())->toBe(20_000);

    // THC: Flat Rp 750.000 per container
    $thc = Surcharge::create([
        'code' => Surcharge::CODE_THC,
        'name' => 'Terminal Handling Charge',
        'type' => 'flat',
        'flat_amount_idr' => 750_000,
        'is_active' => true,
    ]);
    expect($thc->calculate(BigDecimal::of(0), ['container_count' => 2])->toInt())->toBe(1_500_000);
});
