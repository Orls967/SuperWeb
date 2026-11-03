<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Geo\Geohash;

it('encodes exact D80-4 prompt vector to geohash qqguygv1t and verifies pod certificate', function () {
    $geohash = new Geohash;

    $lat = -6.175392;
    $lng = 106.827153;

    $hash = $geohash->encode($lat, $lng, 9);
    expect($hash)->toBe('qqguygv1t');

    $valid = $geohash->verifyPod($hash, $lat, $lng, 9);
    expect($valid)->toBeTrue();
});

it('rejects corrupted pod geohash certificate with InvalidArgumentException', function () {
    $geohash = new Geohash;

    // Titik yang jauh dari geohash Monas Jakarta (qqguygv1t)
    $corruptedLat = -7.257472; // Surabaya
    $corruptedLng = 112.752090;

    expect(fn () => $geohash->verifyPod('qqguygv1t', $corruptedLat, $corruptedLng, 9))
        ->toThrow(InvalidArgumentException::class);
});
