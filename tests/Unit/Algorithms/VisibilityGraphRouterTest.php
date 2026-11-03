<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Geo\VisibilityGraphRouter;

it('solves exact D80-4 prompt vector with lexicographical tie-break and exact length 10246m', function () {
    $router = new VisibilityGraphRouter();

    $hub = ['x' => 0, 'y' => 0];
    $destination = ['x' => 10000, 'y' => 0];
    $squareNoFlyZone = [
        ['x' => 4000, 'y' => -1000],
        ['x' => 6000, 'y' => -1000],
        ['x' => 6000, 'y' => 1000],
        ['x' => 4000, 'y' => 1000],
    ];

    $batteryRange = 15000;

    $result = $router->findRoute($hub, $destination, [$squareNoFlyZone], $batteryRange);

    // Verifikasi rute dan panjang eksak
    expect($result['length_meters'])->toBe(10246);
    expect($result['path'])->toBe([
        ['x' => 0, 'y' => 0],
        ['x' => 4000, 'y' => -1000],
        ['x' => 6000, 'y' => -1000],
        ['x' => 10000, 'y' => 0],
    ]);

    // Verifikasi sertifikat independen
    $valid = $router->verifyCertificate($result, $batteryRange, [$squareNoFlyZone]);
    expect($valid)->toBeTrue();
});

it('rejects corrupted visibility router certificate with InvalidArgumentException', function () {
    $router = new VisibilityGraphRouter();

    $hub = ['x' => 0, 'y' => 0];
    $destination = ['x' => 10000, 'y' => 0];
    $squareNoFlyZone = [
        ['x' => 4000, 'y' => -1000],
        ['x' => 6000, 'y' => -1000],
        ['x' => 6000, 'y' => 1000],
        ['x' => 4000, 'y' => 1000],
    ];

    // Rute palsu yang memotong interior zona no-fly
    $corruptedResult = [
        'path' => [$hub, $destination],
        'length_meters' => 10000,
        'exact_length_meters' => 10000.0,
        'certificate' => ['is_valid' => true],
    ];

    expect(fn () => $router->verifyCertificate($corruptedResult, 15000, [$squareNoFlyZone]))
        ->toThrow(InvalidArgumentException::class);
});
