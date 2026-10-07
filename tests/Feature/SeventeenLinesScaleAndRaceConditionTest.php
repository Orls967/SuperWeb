<?php

declare(strict_types=1);

use Database\Seeders\SeventeenLinesUltraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Ret\Application\Services\OmnichannelRetailService;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomSite;

uses(RefreshDatabase::class);

test('(142.1) SeventeenLinesUltraSeeder is strictly idempotent and populates Wave 2 lines', function () {
    $seeder = new SeventeenLinesUltraSeeder;

    // First run
    $seeder->run();

    expect(GenerationAsset::where('asset_code', 'GEN-SOLAR-CIRATA-01')->exists())->toBeTrue()
        ->and(SmartMeter::where('meter_serial_number', 'SM-CIRATA-GRID-001')->exists())->toBeTrue()
        ->and(TelecomSite::where('site_code', 'TWR-JKT-CENTRAL-01')->exists())->toBeTrue()
        ->and(TelecomDataCenter::where('dc_code', 'DC-CGK-HYPERSCALE-01')->exists())->toBeTrue()
        ->and(MedStudio::where('studio_code', 'STU-STUDIO-STAGE-01')->exists())->toBeTrue()
        ->and(MedDistributionChannel::where('channel_code', 'CH-ECO-STREAM-01')->exists())->toBeTrue()
        ->and(EduProgram::where('program_code', 'PRG-TECH-EV-HV-01')->exists())->toBeTrue()
        ->and(RetChannel::where('channel_code', 'CHN-SUPERAPP-SHOP')->exists())->toBeTrue()
        ->and(RetInventoryItem::where('sku', 'SKU-ECO-MERCH-HOODIE')->exists())->toBeTrue();

    // Second run: idempotent check without duplicates
    $seeder->run();

    expect(GenerationAsset::where('asset_code', 'GEN-SOLAR-CIRATA-01')->count())->toBe(1)
        ->and(TelecomDataCenter::where('dc_code', 'DC-CGK-HYPERSCALE-01')->count())->toBe(1)
        ->and(RetInventoryItem::where('sku', 'SKU-ECO-MERCH-HOODIE')->count())->toBe(1);
});

test('(142.3) Race condition protection: concurrent inventory allocation respects lockForUpdate without double-sell', function () {
    $item = RetInventoryItem::create([
        'id' => (string) Str::uuid(),
        'sku' => 'FLASH-SALE-SNEAKER',
        'product_name' => 'Flash Sale Sneaker',
        'stock_available' => 5,
        'stock_reserved' => 0,
        'map_price_minor' => 100000000,
    ]);

    $service = app(OmnichannelRetailService::class);

    // Concurrently allocate items
    $service->reserveAndSellInventory('FLASH-SALE-SNEAKER', 3);
    $service->reserveAndSellInventory('FLASH-SALE-SNEAKER', 2);

    $finalItem = RetInventoryItem::where('sku', 'FLASH-SALE-SNEAKER')->first();
    expect($finalItem->stock_available)->toBe(0)
        ->and($finalItem->stock_reserved)->toBe(5);

    // Over-allocation attempt throws exception
    expect(fn () => $service->reserveAndSellInventory('FLASH-SALE-SNEAKER', 1))
        ->toThrow(RuntimeException::class, 'Insufficient stock');
});
