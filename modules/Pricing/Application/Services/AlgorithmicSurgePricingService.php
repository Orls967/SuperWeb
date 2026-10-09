<?php

namespace Modules\Pricing\Application\Services;

use Carbon\Carbon;
use Modules\Pricing\Domain\Models\FrozenQuote;
use Modules\Pricing\Domain\Models\PriceTick;

class AlgorithmicSurgePricingService
{
    /**
     * 81.2 Calculate dynamic price per second with strict floor/ceiling guardrails
     */
    public function computeTickPrice(
        string $sku,
        int $baseHppIdr,
        int $hetCeilingIdr,
        float $demandIndex, // e.g. 1.0 = normal, 1.8 = high surge
        ?int $contractPriceIdr = null
    ): array {
        $floorPrice = (int) round($baseHppIdr * 1.15); // 15% minimum margin guardrail
        $ceilingPrice = $hetCeilingIdr;

        // Contract price ALWAYS wins over dynamic surge pricing
        if ($contractPriceIdr !== null && $contractPriceIdr > 0) {
            return [
                'effective_price_idr' => $contractPriceIdr,
                'source' => 'CONTRACT_PRICE',
                'floor_price_idr' => $floorPrice,
                'ceiling_price_idr' => $ceilingPrice,
                'is_surge' => false,
            ];
        }

        // Algorithmic surge calculation: price = baseHpp * 1.20 * demandIndex
        $rawPrice = (int) round($baseHppIdr * 1.20 * $demandIndex);

        // Guardrail enforcement: Clamped strictly between floor and ceiling
        $effectivePrice = min($ceilingPrice, max($floorPrice, $rawPrice));

        return [
            'effective_price_idr' => $effectivePrice,
            'source' => $demandIndex > 1.2 ? 'DEMAND_SURGE' : 'DYNAMIC_ALGO',
            'floor_price_idr' => $floorPrice,
            'ceiling_price_idr' => $ceilingPrice,
            'is_surge' => $demandIndex > 1.2,
        ];
    }

    /**
     * 81.1 Ingest price tick idempotently
     */
    public function recordPriceTick(
        string $sku,
        Carbon $tickTime,
        int $priceIdr,
        float $demandIndex,
        string $driverSource
    ): PriceTick {
        $key = hash('sha256', "{$sku}:{$tickTime->toIso8601String()}:{$priceIdr}:{$driverSource}");

        return PriceTick::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'sku' => $sku,
                'tick_time' => $tickTime,
                'price_idr' => $priceIdr,
                'demand_index' => $demandIndex,
                'driver_source' => $driverSource,
            ]
        );
    }

    /**
     * 81.4 Freeze price quote with cryptographic timelock
     */
    public function freezePriceQuote(
        string $sku,
        int $effectivePriceIdr,
        int $floorPriceIdr,
        int $ceilingPriceIdr,
        int $lockMinutes = 30
    ): FrozenQuote {
        $quoteCode = 'QT-'.strtoupper(bin2hex(random_bytes(6)));
        $lockedUntil = now()->addMinutes($lockMinutes);
        $quoteHash = hash('sha256', "{$quoteCode}:{$sku}:{$effectivePriceIdr}:{$lockedUntil->toIso8601String()}");

        return FrozenQuote::create([
            'quote_code' => $quoteCode,
            'sku' => $sku,
            'frozen_price_idr' => $effectivePriceIdr,
            'floor_price_idr' => $floorPriceIdr,
            'ceiling_price_idr' => $ceilingPriceIdr,
            'quote_hash' => $quoteHash,
            'locked_until' => $lockedUntil,
            'status' => 'LOCKED',
        ]);
    }
}
