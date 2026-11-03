<?php

declare(strict_types=1);

namespace Modules\Pricing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Pricing\Application\Services\DynamicTickEngine;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\SkuParameter;

class GeneratePriceTickCommand extends Command
{
    protected $signature = 'pricing:tick {--sku= : SKU spesifik yang akan diproses}';

    protected $description = 'Hitung dan simpan tick harga dinamis terjadwal (idempoten per slot 5 menit)';

    public function handle(DynamicTickEngine $engine, ClockInterface $clock): int
    {
        $specificSku = $this->option('sku');

        $query = SkuParameter::query();
        if ($specificSku) {
            $query->where('sku', (string) $specificSku);
        }

        $parameters = $query->get();

        if ($parameters->isEmpty() && $specificSku) {
            // Default parameter jika SKU belum terdaftar di SkuParameter
            $parameters = collect([
                new SkuParameter([
                    'sku' => (string) $specificSku,
                    'category' => 'general',
                    'base_price_idr' => 10_000,
                    'floor_price_idr' => 8_000,
                    'ceiling_price_idr' => 15_000,
                    'het_price_idr' => 14_000,
                    'price_step_idr' => 50,
                    'elasticity_bps' => 10_000,
                    'stock_cover_days' => 5, // +500 bps
                    'demand_ratio_bps' => 12_500, // +400 bps
                    'is_perishable' => false,
                ]),
            ]);
        }

        $slot = $engine->get5MinuteSlot($clock->now());
        $processed = 0;

        foreach ($parameters as $param) {
            $sku = (string) $param->sku;

            // Cari harga tick terakhir
            $lastTick = PriceTick::where('sku', $sku)
                ->orderByDesc('tick_time')
                ->first();

            $lastPrice = $lastTick ? (int) $lastTick->price_idr : (int) $param->base_price_idr;

            $result = $engine->calculateTick([
                'sku' => $sku,
                'base_price_idr' => (int) $param->base_price_idr,
                'last_price_idr' => $lastPrice,
                'floor_price_idr' => (int) $param->floor_price_idr,
                'ceiling_price_idr' => (int) $param->ceiling_price_idr,
                'het_price_idr' => (int) $param->het_price_idr,
                'price_step_idr' => (int) $param->price_step_idr,
                'stock_cover_days' => $param->stock_cover_days,
                'demand_ratio_bps' => $param->demand_ratio_bps,
                'shelf_life_ratio_bps' => $param->shelf_life_ratio_bps,
                'season_factor_bps' => $param->season_factor_bps,
                'occupancy_factor_bps' => $param->occupancy_factor_bps,
                'is_perishable' => (bool) $param->is_perishable,
                'slot_time' => $slot,
            ]);

            if ($result['accepted']) {
                $engine->calculateAndPersistTick(
                    sku: $sku,
                    priceIdr: (int) $result['effective_price_idr'],
                    status: (string) $result['status'],
                    slotTime: $slot,
                    basePriceIdr: (int) $result['base_price_idr'],
                    rawPriceIdr: (int) $result['raw_price_idr'],
                    factorTotalBps: (int) $result['factor_total_bps']
                );
            }

            $processed++;
        }

        $this->info("✓ pricing:tick selesai. {$processed} SKU diproses pada slot {$slot->toIso8601String()}.");

        return self::SUCCESS;
    }
}

