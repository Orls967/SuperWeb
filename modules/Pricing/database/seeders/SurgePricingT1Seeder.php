<?php

declare(strict_types=1);

namespace Modules\Pricing\database\seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SurgePricingT1Seeder extends Seeder
{
    public function run(): void
    {
        $baseTime = CarbonImmutable::parse('2026-11-04 00:00:00');

        // 1. Seed 1.000 SKU parameters
        $skuRows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $sku = sprintf('SKU-SIM-%04d', $i);
            $basePrice = 10_000 + ($i % 50) * 100;
            $floorPrice = (int) ($basePrice * 0.8);
            $ceilingPrice = (int) ($basePrice * 1.5);
            $hetPrice = (int) ($basePrice * 1.4);

            $skuRows[] = [
                'sku' => $sku,
                'category' => 'spareparts',
                'base_price_idr' => $basePrice,
                'floor_price_idr' => $floorPrice,
                'ceiling_price_idr' => $ceilingPrice,
                'het_price_idr' => $hetPrice,
                'price_step_idr' => 50,
                'elasticity_bps' => 10_000,
                'is_perishable' => ($i % 5 === 0),
                'stock_cover_days' => 5 + ($i % 10),
                'demand_ratio_bps' => 10_000 + ($i % 20) * 100,
                'season_factor_bps' => 0,
                'occupancy_factor_bps' => 0,
                'version' => 1,
                'created_at' => $baseTime,
                'updated_at' => $baseTime,
            ];

            if (count($skuRows) >= 250) {
                DB::table('pric_sku_parameters')->upsert($skuRows, ['sku']);
                $skuRows = [];
            }
        }
        if (! empty($skuRows)) {
            DB::table('pric_sku_parameters')->upsert($skuRows, ['sku']);
        }

        // 2. Seed 10.000 ticks across SKUs
        $tickRows = [];
        $totalTicks = 10_000;
        for ($j = 1; $j <= $totalTicks; $j++) {
            $skuIndex = (($j - 1) % 1000) + 1;
            $sku = sprintf('SKU-SIM-%04d', $skuIndex);
            $slotOffsetMinutes = (int) (($j - 1) / 1000) * 5;
            $slotTime = $baseTime->addMinutes($slotOffsetMinutes);
            $basePrice = 10_000 + ($skuIndex % 50) * 100;

            // Variasi harga di dalam rentang aman [floor, het]
            $price = $basePrice + (($j % 7) - 3) * 50;

            $tickRows[] = [
                'sku' => $sku,
                'tick_time' => $slotTime,
                'slot_time' => $slotTime,
                'price_idr' => $price,
                'base_price_idr' => $basePrice,
                'raw_price_idr' => $price,
                'factor_total_bps' => 0,
                'demand_index' => 1.0,
                'driver_source' => 'SIMULATION_T1',
                'status' => 'normal',
                'idempotency_key' => "pric:sim:{$sku}:{$j}",
                'created_at' => $slotTime,
                'updated_at' => $slotTime,
            ];

            if (count($tickRows) >= 500) {
                DB::table('pric_price_ticks')->insertOrIgnore($tickRows);
                $tickRows = [];
            }
        }
        if (! empty($tickRows)) {
            DB::table('pric_price_ticks')->insertOrIgnore($tickRows);
        }
    }
}
