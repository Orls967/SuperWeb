<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Services\HotelFranchiseAndBrandService;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(HotelFranchiseAndBrandService::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('conducts brand standard audit with various score tiers', function () {
    $service = app(HotelFranchiseAndBrandService::class);

    // 5 Star Luxury (>= 90%)
    $audit90 = $service->conductBrandAudit($this->property, 190, 200);
    expect($audit90->simulated_star_grade)->toBe('5_STAR_LUXURY')
        ->and($audit90->listing_status)->toBe('ACTIVE');

    // 4 Star (>= 75%)
    $audit75 = $service->conductBrandAudit($this->property, 160, 200);
    expect($audit75->simulated_star_grade)->toBe('4_STAR')
        ->and($audit75->listing_status)->toBe('ACTIVE');

    // 3 Star (>= 60%)
    $audit60 = $service->conductBrandAudit($this->property, 130, 200);
    expect($audit60->simulated_star_grade)->toBe('3_STAR')
        ->and($audit60->listing_status)->toBe('ACTION_PLAN_REQUIRED');

    // Ungraded / Suspended (< 60%)
    $audit50 = $service->conductBrandAudit($this->property, 100, 200);
    expect($audit50->simulated_star_grade)->toBe('UNGRADED')
        ->and($audit50->listing_status)->toBe('SUSPENDED');
});
