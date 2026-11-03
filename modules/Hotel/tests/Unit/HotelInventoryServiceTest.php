<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(HotelInventoryService::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('calculates available room count accurately accounting for sold and out of order', function () {
    $service = new HotelInventoryService;
    $date = now()->addDays(3)->toDateString();

    $initialCount = $service->getAvailableCount($this->property->id, $this->roomType->id, $date);
    expect($initialCount)->toBeGreaterThan(0);

    // Check availability range before out of order
    $range = $service->checkAvailabilityRange(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        checkInDate: now()->addDays(3)->toDateString(),
        checkOutDate: now()->addDays(5)->toDateString()
    );

    expect($range['is_available'])->toBeTrue()
        ->and($range['min_available'])->toBeGreaterThan(0)
        ->and(count($range['dates']))->toBe(2);

    // Tandai 1 kamar out of order
    $this->room->update(['status' => 'out_of_order']);
    $afterOoo = $service->getAvailableCount($this->property->id, $this->roomType->id, $date);
    expect($afterOoo)->toBeLessThan($initialCount);
});
