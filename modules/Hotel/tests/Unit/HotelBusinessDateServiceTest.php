<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelBusinessDate;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(HotelBusinessDateService::class);

function createTestProperty(): HotelProperty
{
    return HotelProperty::create([
        'id' => (string) Str::uuid(),
        'property_code' => 'PROP-'.Str::upper(Str::random(6)),
        'name' => 'Test Hotel',
        'property_type' => 'CITY_HOTEL',
        'city' => 'Jakarta',
        'total_rooms' => 100,
    ]);
}

it('initializes and returns business date', function () {
    $prop = createTestProperty();
    $service = new HotelBusinessDateService;
    $record = $service->initializeBusinessDate($prop->id, '2026-11-01');

    expect($record->property_id)->toBe($prop->id)
        ->and(Carbon::parse($record->current_business_date)->toDateString())->toBe('2026-11-01')
        ->and($record->status)->toBe('open');

    // Update existing
    $updated = $service->initializeBusinessDate($prop->id, '2026-11-02');
    expect(Carbon::parse($updated->current_business_date)->toDateString())->toBe('2026-11-02');

    $curDate = $service->getCurrentBusinessDate($prop->id);
    expect($curDate)->toBe('2026-11-02');

    $fetched = $service->getBusinessDateRecord($prop->id);
    expect($fetched->id)->toBe($updated->id);
});

it('uses clock interface to initialize date if not exists', function () {
    $prop = createTestProperty();
    $clock = Mockery::mock(ClockInterface::class);
    $clock->shouldReceive('now')->andReturn(Carbon::parse('2026-11-05 10:00:00'));

    $service = new HotelBusinessDateService($clock);
    $curDate = $service->getCurrentBusinessDate($prop->id);

    expect($curDate)->toBe('2026-11-05');
    $record = HotelBusinessDate::where('property_id', $prop->id)->first();
    expect($record)->not->toBeNull()
        ->and(Carbon::parse($record->current_business_date)->toDateString())->toBe('2026-11-05')
        ->and($record->status)->toBe('open')
        ->and($record->last_checkpoint)->toBeNull()
        ->and($record->audited_at)->toBeNull();
});

it('auto initializes when getBusinessDateRecord called for uninitialized property', function () {
    $prop = createTestProperty();
    $service = new HotelBusinessDateService;

    $record = $service->getBusinessDateRecord($prop->id);
    expect($record)->toBeInstanceOf(HotelBusinessDate::class)
        ->and($record->property_id)->toBe($prop->id)
        ->and($record->status)->toBe('open')
        ->and($record->last_checkpoint)->toBeNull()
        ->and($record->audited_at)->toBeNull();
});
