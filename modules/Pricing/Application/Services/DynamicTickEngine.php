<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\PriceTickRejection;
use Modules\Pricing\Domain\Models\SkuParameter;

class DynamicTickEngine
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {}

    /**
     * D81-2 Hitung tick harga dinamis deterministik tingkat S1.
     *
     * @param array{
     *     sku: string,
     *     base_price_idr: int,
     *     last_price_idr: int,
     *     floor_price_idr: int,
     *     ceiling_price_idr: int,
     *     het_price_idr: int,
     *     price_step_idr?: int,
     *     factors?: array<string, int>,
     *     stock_cover_days?: int|null,
     *     demand_ratio_bps?: int|null,
     *     shelf_life_ratio_bps?: int|null,
     *     is_perishable?: bool,
     *     slot_time?: CarbonInterface|null,
     * } $params
     * @return array{
     *     sku: string,
     *     effective_price_idr: int,
     *     attempted_price_idr: int,
     *     base_price_idr: int,
     *     last_price_idr: int,
     *     raw_price_idr: int,
     *     bounded_price_idr: int,
     *     floor_price_idr: int,
     *     ceiling_price_idr: int,
     *     het_price_idr: int,
     *     status: string,
     *     accepted: bool,
     *     rejection_reason: string|null,
     *     factor_total_bps: int,
     * }
     */
    public function calculateTick(array $params): array
    {
        $sku = (string) $params['sku'];
        $basePrice = (int) $params['base_price_idr'];
        $lastPrice = (int) $params['last_price_idr'];
        $floorPrice = (int) $params['floor_price_idr'];
        $ceilingPrice = (int) $params['ceiling_price_idr'];
        $hetPrice = (int) $params['het_price_idr'];
        $step = isset($params['price_step_idr']) && $params['price_step_idr'] > 0 ? (int) $params['price_step_idr'] : 50;

        $factors = $params['factors'] ?? [];

        // 1. f_stok
        if (isset($factors['f_stok'])) {
            $fStok = (int) $factors['f_stok'];
        } elseif (isset($params['stock_cover_days'])) {
            $cover = (int) $params['stock_cover_days'];
            if ($cover < 3) {
                $fStok = 1500;
            } elseif ($cover < 7) {
                $fStok = 500;
            } elseif ($cover < 14) {
                $fStok = 0;
            } else {
                $fStok = -700;
            }
        } else {
            $fStok = 0;
        }

        // 2. f_permintaan
        if (isset($factors['f_permintaan'])) {
            $fPermintaan = (int) $factors['f_permintaan'];
        } elseif (isset($params['demand_ratio_bps'])) {
            $ratio = (int) $params['demand_ratio_bps'];
            if ($ratio >= 15000) {
                $fPermintaan = 1000;
            } elseif ($ratio >= 12000) {
                $fPermintaan = 400;
            } elseif ($ratio < 8000) {
                $fPermintaan = -500;
            } else {
                $fPermintaan = 0;
            }
        } else {
            $fPermintaan = 0;
        }

        // 3. f_umur_simpan
        $isPerishable = (bool) ($params['is_perishable'] ?? false);
        if (isset($factors['f_umur_simpan'])) {
            $fUmurSimpan = (int) $factors['f_umur_simpan'];
        } elseif ($isPerishable && isset($params['shelf_life_ratio_bps'])) {
            $sisaRatio = (int) $params['shelf_life_ratio_bps'];
            if ($sisaRatio < 2000) {
                $fUmurSimpan = -3000;
            } elseif ($sisaRatio < 5000) {
                $fUmurSimpan = -1000;
            } else {
                $fUmurSimpan = 0;
            }
        } else {
            $fUmurSimpan = 0;
        }

        // 4. f_musim & f_okupansi
        $fMusim = (int) ($factors['f_musim'] ?? $params['season_factor_bps'] ?? 0);
        $fOkupansi = (int) ($factors['f_okupansi'] ?? $params['occupancy_factor_bps'] ?? 0);

        $totalFaktorBps = $fStok + $fPermintaan + $fUmurSimpan + $fMusim + $fOkupansi;

        // Langkah 1: harga_mentah
        $mentahRupiah = (int) round(($basePrice * (10000 + $totalFaktorBps)) / 10000, 0, PHP_ROUND_HALF_UP);
        $hargaMentah = (int) (round($mentahRupiah / $step, 0, PHP_ROUND_HALF_UP) * $step);

        // Langkah 2: Batas langkah (±1.000 bps dari tick terakhir)
        $maxStep = (int) round(($lastPrice * 11000) / 10000, 0, PHP_ROUND_HALF_UP);
        $minStep = (int) round(($lastPrice * 9000) / 10000, 0, PHP_ROUND_HALF_UP);

        $status = 'normal';
        $boundedPrice = $hargaMentah;

        if ($hargaMentah > $maxStep) {
            $boundedPrice = $maxStep;
            $status = 'dibatasi';
        } elseif ($hargaMentah < $minStep) {
            $boundedPrice = $minStep;
            $status = 'dibatasi';
        }

        // Langkah 3: Guardrail keras [floor, min(ceiling, HET)]
        $accepted = true;
        $rejectionReason = null;
        $effectivePrice = $boundedPrice;

        if ($boundedPrice < $floorPrice) {
            $accepted = false;
            $rejectionReason = 'BELOW_FLOOR';
            $effectivePrice = $lastPrice;
        } elseif ($boundedPrice > $hetPrice) {
            $accepted = false;
            $rejectionReason = 'ABOVE_HET';
            $effectivePrice = $lastPrice;
        } elseif ($boundedPrice > $ceilingPrice) {
            $accepted = false;
            $rejectionReason = 'ABOVE_CEILING';
            $effectivePrice = $lastPrice;
        }

        $slotTime = isset($params['slot_time'])
            ? CarbonImmutable::createFromInterface($params['slot_time'])
            : $this->get5MinuteSlot($this->clock->now());

        if (! $accepted) {
            $idempotencyKey = "pric:rej:{$sku}:{$slotTime->timestamp}";
            PriceTickRejection::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'sku' => $sku,
                    'slot_time' => $slotTime,
                    'attempted_price_idr' => $boundedPrice,
                    'base_price_idr' => $basePrice,
                    'last_price_idr' => $lastPrice,
                    'raw_price_idr' => $hargaMentah,
                    'bounded_price_idr' => $boundedPrice,
                    'floor_price_idr' => $floorPrice,
                    'ceiling_price_idr' => $ceilingPrice,
                    'het_price_idr' => $hetPrice,
                    'rejection_reason' => $rejectionReason,
                ]
            );
        }

        return [
            'sku' => $sku,
            'effective_price_idr' => $effectivePrice,
            'attempted_price_idr' => $boundedPrice,
            'base_price_idr' => $basePrice,
            'last_price_idr' => $lastPrice,
            'raw_price_idr' => $hargaMentah,
            'bounded_price_idr' => $boundedPrice,
            'floor_price_idr' => $floorPrice,
            'ceiling_price_idr' => $ceilingPrice,
            'het_price_idr' => $hetPrice,
            'status' => $status,
            'accepted' => $accepted,
            'rejection_reason' => $rejectionReason,
            'factor_total_bps' => $totalFaktorBps,
        ];
    }

    /**
     * D81-3 Proyeksi dampak kenaikan harga terhadap volume permintaan (Item 64.1).
     */
    public function projectDemandWithElasticity(
        int $baseQuantity,
        int $elasticityBps,
        int $deltaPriceBps
    ): int {
        // q' = q * (10000 - e * dp_bps / 10000) / 10000
        $impactBps = (int) round(($elasticityBps * $deltaPriceBps) / 10000, 0, PHP_ROUND_HALF_UP);
        $projected = (int) round(($baseQuantity * (10000 - $impactBps)) / 10000, 0, PHP_ROUND_HALF_UP);

        return max(0, $projected);
    }

    /**
     * Simpan tick harga baru secara idempoten per (SKU, slot 5 menit).
     */
    public function calculateAndPersistTick(
        string $sku,
        int $priceIdr,
        string $status = 'normal',
        ?CarbonInterface $slotTime = null,
        ?int $basePriceIdr = null,
        ?int $rawPriceIdr = null,
        ?int $factorTotalBps = null
    ): PriceTick {
        $now = $this->clock->now();
        $slot = isset($slotTime)
            ? CarbonImmutable::createFromInterface($slotTime)
            : $this->get5MinuteSlot($now);

        $idempotencyKey = "pric:tick:{$sku}:{$slot->timestamp}";

        return PriceTick::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'sku' => $sku,
                'tick_time' => CarbonImmutable::createFromInterface($now),
                'slot_time' => $slot,
                'price_idr' => $priceIdr,
                'base_price_idr' => $basePriceIdr ?? $priceIdr,
                'raw_price_idr' => $rawPriceIdr ?? $priceIdr,
                'factor_total_bps' => $factorTotalBps ?? 0,
                'demand_index' => 1.0,
                'driver_source' => 'DYNAMIC_SURGE',
                'status' => $status,
            ]
        );
    }

    /**
     * Cari atau hitung slot 5 menit.
     */
    public function get5MinuteSlot(CarbonInterface $time): CarbonImmutable
    {
        $minute = (int) $time->format('i');
        $flooredMinute = $minute - ($minute % 5);

        return CarbonImmutable::createFromInterface($time)
            ->minute($flooredMinute)
            ->second(0);
    }
}

