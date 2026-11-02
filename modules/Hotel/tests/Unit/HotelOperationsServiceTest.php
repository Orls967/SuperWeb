<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(HotelOperationsService::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('creates reservation via hotel operations service', function () {
    $service = app(HotelOperationsService::class);
    $res = $service->createReservation(
        propertyId: $this->property->id,
        roomType: 'suite',
        guestUserId: (string) $this->guest->id,
        checkInDate: now()->addDays(2)->toDateString(),
        checkOutDate: now()->addDays(4)->toDateString(),
        requestedRateIdr: 5000000
    );

    expect($res->status)->toBe('confirmed')
        ->and($res->property_id)->toBe($this->property->id);
});
