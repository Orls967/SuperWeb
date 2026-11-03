<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelReportService;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(HotelReportService::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('returns default zero values when no snapshot exists for date', function () {
    $service = new HotelReportService;
    $report = $service->getDailyReport($this->property->id, '2026-11-20');

    expect($report['total_rooms'])->toBe(0)
        ->and($report['occupied_rooms'])->toBe(0)
        ->and($report['occupancy_rate_percent'])->toBe(0.0)
        ->and($report['adr_idr'])->toBe(0)
        ->and($report['revpar_idr'])->toBe(0)
        ->and($report['is_reconciled'])->toBeTrue();
});

it('returns daily report mapped from existing snapshot', function () {
    HotelDailyRevenueSnapshot::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'business_date' => '2026-11-01',
        'total_rooms' => 100,
        'occupied_rooms' => 80,
        'out_of_order_rooms' => 5,
        'available_rooms' => 95,
        'occupancy_rate_bps' => 8000,
        'total_room_revenue_idr' => 0,
        'total_fnb_revenue_idr' => 20000000,
        'total_service_charge_idr' => 10000000,
        'total_tax_pbjt_idr' => 11000000,
        'adr_idr' => 1000000,
        'revpar_idr' => 800000,
        'ledger_room_revenue_idr' => 0,
        'discrepancy_amount_idr' => 0,
        'is_reconciled' => true,
    ]);

    $service = new HotelReportService;
    $report = $service->getDailyReport($this->property->id, '2026-11-01');

    expect($report['total_rooms'])->toBe(100)
        ->and($report['occupied_rooms'])->toBe(80)
        ->and($report['occupancy_rate_percent'])->toBe(80.0)
        ->and($report['adr_idr'])->toBe(1000000)
        ->and($report['revpar_idr'])->toBe(800000)
        ->and($report['is_reconciled'])->toBeTrue();

    $range = $service->getDateRangeReport($this->property->id, '2026-11-01', '2026-11-05');
    expect($range)->toHaveCount(1);
});
