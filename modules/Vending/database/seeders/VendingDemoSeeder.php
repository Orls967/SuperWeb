<?php

declare(strict_types=1);

namespace Modules\Vending\database\seeders;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Domain\Models\VendingUnit;

class VendingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);

        $unit = VendingUnit::firstOrCreate(
            ['unit_code' => 'VEN-DUTA-01'],
            [
                'location_name' => 'Duta Mall Ground Floor Kiosk',
                'status' => 'ACTIVE',
                'current_stock' => 50,
                'critical_threshold' => 10,
                'max_capacity' => 100,
                'total_sales_count' => 0,
                'total_sales_idr' => 0,
                'sensor_status' => [
                    'coin_slot' => 'OK',
                    'optical_drop' => 'OK',
                    'door_lock' => 'OK',
                ],
            ]
        );

        /** @var SmartVendingService $service */
        $service = app(SmartVendingService::class);

        // Generate initial forecast
        $forecast = $service->generateDemandAndWasteForecast(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'SNK-001',
            forecastDate: Carbon::parse('2026-10-20'),
            historicalSales: [100, 105, 110, 115, 120, 125, 130],
            footfallFactor: 110
        );

        $service->recordActualDemandAndWaste($forecast, 135, 12);

        // Generate initial Auto-PO
        $service->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-001',
            forecastQty: 120,
            currentStock: 30,
            unitCostIdr: 40_000,
            dailyPlafonIdr: 15_000_000
        );
    }
}
