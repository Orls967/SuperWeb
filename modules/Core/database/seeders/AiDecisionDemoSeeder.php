<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\Ai\DynamicPricingEngineService;
use Modules\Core\Application\Services\Ai\StockPrescriptionEngineService;
use Modules\Core\Application\Services\Ai\TransactionAnomalyDetectorService;
use Modules\Core\Domain\Models\CoreAiPricingRule;

class AiDecisionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pricingItems = [
            ['code' => 'MENU-NASI-GORENG-SPECIAL', 'name' => 'Nasi Goreng Kambing Special', 'base' => 45000, 'floor' => 32000, 'ceiling' => 60000],
            ['code' => 'MENU-MIE-AYAM-JAMUR', 'name' => 'Mie Ayam Jamur Pangsit', 'base' => 35000, 'floor' => 25000, 'ceiling' => 48000],
            ['code' => 'BEV-KOPI-SUSU-GULA-AREN', 'name' => 'Kopi Susu Gula Aren 500ml', 'base' => 22000, 'floor' => 16000, 'ceiling' => 30000],
            ['code' => 'BEV-ES-TEH-LEMON', 'name' => 'Es Teh Lemon Mint', 'base' => 15000, 'floor' => 10000, 'ceiling' => 20000],
            ['code' => 'RETAIL-ROTI-TAWAR-GANDUM', 'name' => 'Roti Tawar Gandum Utuh', 'base' => 24000, 'floor' => 15000, 'ceiling' => 32000],
            ['code' => 'RETAIL-SUSU-UHT-1L', 'name' => 'Susu UHT Full Cream 1 Liter', 'base' => 21000, 'floor' => 16000, 'ceiling' => 27000],
            ['code' => 'SNACK-KENTANG-CRUNCHY', 'name' => 'Kentang Goreng Truffle Cheese', 'base' => 32000, 'floor' => 22000, 'ceiling' => 45000],
            ['code' => 'SNACK-PISANG-GORENG-MADU', 'name' => 'Pisang Goreng Wijen Madu', 'base' => 25000, 'floor' => 18000, 'ceiling' => 35000],
            ['code' => 'DISH-SATE-AYAM-10TUSUK', 'name' => 'Sate Ayam Madura 10 Tusuk', 'base' => 42000, 'floor' => 30000, 'ceiling' => 55000],
            ['code' => 'DISH-SOP-BUNTUT-PREMIUM', 'name' => 'Sop Buntut Sapi Kuah Rempah', 'base' => 95000, 'floor' => 75000, 'ceiling' => 130000],
        ];

        foreach ($pricingItems as $item) {
            CoreAiPricingRule::updateOrCreate(
                ['item_code' => $item['code']],
                [
                    'id' => (string) Str::uuid(),
                    'rule_code' => 'PR-'.Str::upper(Str::random(8)),
                    'item_name' => $item['name'],
                    'channel' => 'ALL',
                    'base_price_idr' => $item['base'],
                    'floor_price_idr' => $item['floor'],
                    'ceiling_price_idr' => $item['ceiling'],
                    'elasticity_coefficient' => -0.50,
                    'max_surge_multiplier' => 1.50,
                    'decay_hours_threshold' => 24,
                    'is_active' => true,
                ]
            );
        }

        $pricingEngine = app(DynamicPricingEngineService::class);
        $seed = 1000;
        foreach ($pricingItems as $item) {
            $pricingEngine->calculatePrice(
                itemCode: $item['code'],
                basePriceIdr: $item['base'],
                currentStock: 40,
                hoursToExpiry: 12,
                spaceOccupancyRate: 0.75,
                recentSalesVelocity: 20,
                baselineSalesVelocity: 15,
                seed: ++$seed
            );
            $pricingEngine->calculatePrice(
                itemCode: $item['code'],
                basePriceIdr: $item['base'],
                currentStock: 15,
                hoursToExpiry: 4,
                spaceOccupancyRate: 0.90,
                recentSalesVelocity: 35,
                baselineSalesVelocity: 15,
                seed: ++$seed
            );
        }

        $anomalyDetector = app(TransactionAnomalyDetectorService::class);
        for ($i = 1; $i <= 10; $i++) {
            $anomalyDetector->evaluateTransaction(
                transactionRef: "TX-NORM-DEMO-{$i}",
                customerRef: "CUST-DEMO-{$i}",
                amountIdr: 125000 * $i,
                channel: 'POS',
                recentTxCount10m: 1,
                avgHistoricalAmountIdr: 120000 * $i,
                stdDevHistoricalAmountIdr: 15000 * $i,
                seed: ++$seed
            );
        }

        for ($i = 1; $i <= 4; $i++) {
            $anomalyDetector->evaluateTransaction(
                transactionRef: "TX-FRAUD-DEMO-{$i}",
                customerRef: "CUST-RISK-{$i}",
                amountIdr: 65000000 + ($i * 10000000),
                channel: 'API_GATEWAY',
                recentTxCount10m: 7 + $i,
                avgHistoricalAmountIdr: 1000000,
                stdDevHistoricalAmountIdr: 200000,
                isLateNight: true,
                isSplitBillPattern: true,
                seed: ++$seed
            );
        }

        $stockEngine = app(StockPrescriptionEngineService::class);
        $stockItems = [
            ['code' => 'SKU-BERAS-ROJOLELE', 'name' => 'Beras Rojolele 10kg', 'stock' => 50, 'demand' => 15, 'lead' => 4, 'cost' => 135000, 'season' => 'RAMADAN_LEBARAN'],
            ['code' => 'SKU-MINYAK-GORENG-2L', 'name' => 'Minyak Goreng Sawit 2 Liter', 'stock' => 40, 'demand' => 25, 'lead' => 3, 'cost' => 34000, 'season' => 'RAMADAN_LEBARAN'],
            ['code' => 'SKU-GULA-PASIR-1KG', 'name' => 'Gula Pasir Kristal 1kg', 'stock' => 20, 'demand' => 18, 'lead' => 3, 'cost' => 16500, 'season' => 'REGULAR'],
            ['code' => 'PART-BAN-TRUK-1000R20', 'name' => 'Ban Luar Truk Logistik Radial', 'stock' => 2, 'demand' => 3, 'lead' => 7, 'cost' => 2400000, 'season' => 'RAINY_SEASON'],
            ['code' => 'PART-ACCU-HEAVY-120AH', 'name' => 'Aki Kering Truk 12V 120Ah', 'stock' => 1, 'demand' => 2, 'lead' => 5, 'cost' => 1850000, 'season' => 'REGULAR'],
        ];

        foreach ($stockItems as $item) {
            $stockEngine->generateRecommendation(
                itemCode: $item['code'],
                itemName: $item['name'],
                currentStock: $item['stock'],
                avgDailyDemand: $item['demand'],
                leadTimeDays: $item['lead'],
                unitCostIdr: $item['cost'],
                seasonCode: $item['season'],
                seed: ++$seed
            );
        }
    }
}
