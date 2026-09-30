<?php

declare(strict_types=1);

use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;

beforeEach(function () {
    $this->calculator = new ChargeableWeightCalculator;
});

test('road courier uses actual weight when greater and rounds up to next full 1 kg', function () {
    // 2.3 kg actual, small dimensions (10x10x10 cm = 1000 cm³ / 6000 = 0.167 kg vol)
    $packages = [
        ['weight_g' => 2300, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $result = $this->calculator->calculate($packages, TransportMode::ROAD, ServiceLevel::Regular);

    expect($result->actualWeightKg->toFloat())->toBe(2.3)
        ->and($result->chargeableWeightKg->toFloat())->toBe(3.0)
        ->and($result->chargeableWeightGrams)->toBe(3000);
});

test('road courier uses volumetric weight when greater and rounds up to next full 1 kg', function () {
    // 500g actual, dimensions: 300x300x300 mm = 30x30x30 cm = 27,000 cm³
    // Volumetric = 27,000 / 6000 = 4.5 kg -> rounded up to 5.0 kg
    $packages = [
        ['weight_g' => 500, 'length_mm' => 300, 'width_mm' => 300, 'height_mm' => 300],
    ];

    $result = $this->calculator->calculate($packages, TransportMode::ROAD, ServiceLevel::Express);

    expect($result->chargeableWeightKg->toFloat())->toBe(5.0)
        ->and($result->chargeableWeightGrams)->toBe(5000);
});

test('road courier enforces minimum 1 kg', function () {
    // 200g actual, small envelope
    $packages = [
        ['weight_g' => 200, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 50],
    ];

    $result = $this->calculator->calculate($packages, TransportMode::ROAD, ServiceLevel::Regular);

    expect($result->chargeableWeightKg->toFloat())->toBe(1.0)
        ->and($result->chargeableWeightGrams)->toBe(1000);
});

test('road courier exact integer weight is not rounded up to next integer', function () {
    // Exactly 2000g, small box
    $packages = [
        ['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
    ];

    $result = $this->calculator->calculate($packages, TransportMode::ROAD, ServiceLevel::SameDay);

    expect($result->chargeableWeightKg->toFloat())->toBe(2.0)
        ->and($result->chargeableWeightGrams)->toBe(2000);
});

test('air freight rounds up to nearest 0.5 kg and enforces minimum 5 kg', function () {
    // 1.2 kg actual -> below min 5 kg -> should be 5.0 kg
    $smallPkg = [
        ['weight_g' => 1200, 'length_mm' => 150, 'width_mm' => 150, 'height_mm' => 150],
    ];
    $resultSmall = $this->calculator->calculate($smallPkg, TransportMode::AIR, ServiceLevel::AirFreight);
    expect($resultSmall->chargeableWeightKg->toFloat())->toBe(5.0);

    // 5.1 kg actual -> rounded up to 5.5 kg
    $pkg51 = [
        ['weight_g' => 5100, 'length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200],
    ];
    $result51 = $this->calculator->calculate($pkg51, TransportMode::AIR, ServiceLevel::AirFreight);
    expect($result51->chargeableWeightKg->toFloat())->toBe(5.5);

    // 5.6 kg actual -> rounded up to 6.0 kg
    $pkg56 = [
        ['weight_g' => 5600, 'length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200],
    ];
    $result56 = $this->calculator->calculate($pkg56, TransportMode::AIR, ServiceLevel::AirFreight);
    expect($result56->chargeableWeightKg->toFloat())->toBe(6.0);

    // 5.5 kg exact -> stays 5.5 kg
    $pkg55 = [
        ['weight_g' => 5500, 'length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200],
    ];
    $result55 = $this->calculator->calculate($pkg55, TransportMode::AIR, ServiceLevel::AirFreight);
    expect($result55->chargeableWeightKg->toFloat())->toBe(5.5);
});

test('sea lcl uses weight or measurement and enforces minimum 1 rt', function () {
    // Weight dominant: 2,500 kg, 1.2 CBM -> 2.5 RT -> 2500 kg
    // 1.2 CBM = 1000mm x 1000mm x 1200mm
    $pkgWeight = [
        ['weight_g' => 2500000, 'length_mm' => 1000, 'width_mm' => 1000, 'height_mm' => 1200],
    ];
    $resultWeight = $this->calculator->calculate($pkgWeight, TransportMode::SEA, ServiceLevel::LCL);
    expect($resultWeight->revenueTons->toFloat())->toBe(2.5)
        ->and($resultWeight->chargeableWeightKg->toFloat())->toBe(2500.0);

    // Measurement dominant: 600 kg, 2.5 CBM -> 2.5 RT -> 2500 kg
    // 2.5 CBM = 1000mm x 1000mm x 2500mm
    $pkgVol = [
        ['weight_g' => 600000, 'length_mm' => 1000, 'width_mm' => 1000, 'height_mm' => 2500],
    ];
    $resultVol = $this->calculator->calculate($pkgVol, TransportMode::SEA, ServiceLevel::LCL);
    expect($resultVol->revenueTons->toFloat())->toBe(2.5)
        ->and($resultVol->chargeableWeightKg->toFloat())->toBe(2500.0);

    // Small shipment: 200 kg, 0.3 CBM -> min 1.0 RT enforced -> 1000 kg
    $pkgSmall = [
        ['weight_g' => 200000, 'length_mm' => 1000, 'width_mm' => 500, 'height_mm' => 600],
    ];
    $resultSmall = $this->calculator->calculate($pkgSmall, TransportMode::SEA, ServiceLevel::LCL);
    expect($resultSmall->revenueTons->toFloat())->toBe(1.0)
        ->and($resultSmall->chargeableWeightKg->toFloat())->toBe(1000.0);
});

test('ftl and fcl are unit based', function () {
    $packages = [
        ['weight_g' => 15000000, 'length_mm' => 12000, 'width_mm' => 2400, 'height_mm' => 2400],
    ];

    $resultFtl = $this->calculator->calculate($packages, TransportMode::ROAD, ServiceLevel::FTL);
    expect($resultFtl->isUnitBased)->toBeTrue();

    $resultFcl = $this->calculator->calculate($packages, TransportMode::SEA, ServiceLevel::FCL);
    expect($resultFcl->isUnitBased)->toBeTrue();
});
