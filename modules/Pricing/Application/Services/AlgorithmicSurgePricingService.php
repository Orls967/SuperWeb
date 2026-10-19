<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\Carbon;
use DomainException;
use InvalidArgumentException;
use Modules\Pricing\Domain\Models\FrozenQuote;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\PriceTickAggregate;
use Modules\Pricing\Domain\Models\PriceWaterfallLog;
use RuntimeException;

class AlgorithmicSurgePricingService
{
    /**
     * 81.2 & 81.3 Calculate dynamic price per second with strict floor/ceiling guardrails,
     * daily band limits, contract price supremacy, and waterfall audit trail.
     */
    public function computeTickPrice(
        string $sku,
        int $baseHppIdr,
        int $hetCeilingIdr,
        float $demandIndex, // e.g. 1.0 = normal, 1.8 = high surge
        ?int $contractPriceIdr = null,
        ?int $previousCloseIdr = null,
        string $channel = 'STORE_B2C'
    ): array {
        $floorPrice = (int) round($baseHppIdr * 1.15); // 15% minimum margin guardrail
        $ceilingPrice = $hetCeilingIdr;

        // Daily band limit (±35% from previous close)
        if ($previousCloseIdr !== null && $previousCloseIdr > 0) {
            $dailyMax = (int) round($previousCloseIdr * 1.35);
            $dailyMin = (int) round($previousCloseIdr * 0.65);
            $ceilingPrice = min($ceilingPrice, $dailyMax);
            $floorPrice = max($floorPrice, $dailyMin);
        }

        // 81.3 Contract price ALWAYS wins over dynamic surge pricing
        if ($contractPriceIdr !== null && $contractPriceIdr > 0) {
            $effectivePrice = $contractPriceIdr;
            $source = 'CONTRACT_PRICE';

            $waterfall = PriceWaterfallLog::create([
                'sku' => $sku,
                'channel' => $channel,
                'base_hpp_idr' => $baseHppIdr,
                'demand_surge_delta_idr' => 0,
                'contract_override_delta_idr' => $contractPriceIdr - $baseHppIdr,
                'guardrail_clamp_delta_idr' => 0,
                'final_price_idr' => $effectivePrice,
                'applied_driver' => $source,
                'created_at' => now(),
            ]);

            return [
                'effective_price_idr' => $effectivePrice,
                'source' => $source,
                'floor_price_idr' => $floorPrice,
                'ceiling_price_idr' => $ceilingPrice,
                'is_surge' => false,
                'waterfall_id' => $waterfall->id,
            ];
        }

        // Algorithmic surge calculation: price = baseHpp * 1.20 * demandIndex
        $baselineMarginPrice = (int) round($baseHppIdr * 1.20);
        $rawPrice = (int) round($baselineMarginPrice * $demandIndex);

        // Guardrail enforcement: Clamped strictly between floor and ceiling
        $effectivePrice = min($ceilingPrice, max($floorPrice, $rawPrice));
        $source = $demandIndex > 1.2 ? 'DEMAND_SURGE' : 'DYNAMIC_ALGO';

        $demandSurgeDelta = $rawPrice - $baselineMarginPrice;
        $guardrailClampDelta = $effectivePrice - $rawPrice;

        $waterfall = PriceWaterfallLog::create([
            'sku' => $sku,
            'channel' => $channel,
            'base_hpp_idr' => $baseHppIdr,
            'demand_surge_delta_idr' => $demandSurgeDelta,
            'contract_override_delta_idr' => 0,
            'guardrail_clamp_delta_idr' => $guardrailClampDelta,
            'final_price_idr' => $effectivePrice,
            'applied_driver' => $source,
            'created_at' => now(),
        ]);

        return [
            'effective_price_idr' => $effectivePrice,
            'source' => $source,
            'floor_price_idr' => $floorPrice,
            'ceiling_price_idr' => $ceilingPrice,
            'is_surge' => $demandIndex > 1.2,
            'waterfall_id' => $waterfall->id,
        ];
    }

    /**
     * 81.1 Ingest price tick idempotently and maintain minute aggregates
     */
    public function recordPriceTick(
        string $sku,
        Carbon $tickTime,
        int $priceIdr,
        float $demandIndex,
        string $driverSource
    ): PriceTick {
        $key = hash('sha256', "{$sku}:{$tickTime->toIso8601String()}:{$priceIdr}:{$driverSource}");

        $tick = PriceTick::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'sku' => $sku,
                'tick_time' => $tickTime,
                'price_idr' => $priceIdr,
                'demand_index' => $demandIndex,
                'driver_source' => $driverSource,
            ]
        );

        if (! $tick->wasRecentlyCreated) {
            return $tick;
        }

        $bucketMinute = $tickTime->copy()->startOfMinute();
        $aggregate = PriceTickAggregate::where('sku', $sku)
            ->where('bucket_minute', $bucketMinute)
            ->first();

        if (! $aggregate) {
            PriceTickAggregate::create([
                'sku' => $sku,
                'bucket_minute' => $bucketMinute,
                'open_price_idr' => $priceIdr,
                'high_price_idr' => $priceIdr,
                'low_price_idr' => $priceIdr,
                'close_price_idr' => $priceIdr,
                'tick_count' => 1,
                'avg_demand_index' => $demandIndex,
            ]);
        } else {
            $newCount = $aggregate->tick_count + 1;
            $currentDemand = (float) $aggregate->avg_demand_index;
            $newAvgDemand = round((($currentDemand * $aggregate->tick_count) + $demandIndex) / $newCount, 2);

            $aggregate->update([
                'high_price_idr' => max($aggregate->high_price_idr, $priceIdr),
                'low_price_idr' => min($aggregate->low_price_idr, $priceIdr),
                'close_price_idr' => $priceIdr,
                'tick_count' => $newCount,
                'avg_demand_index' => $newAvgDemand,
            ]);
        }

        return $tick;
    }

    /**
     * 81.4 Freeze price quote with cryptographic timelock (deterministic A4 compliance)
     */
    public function freezePriceQuote(
        string $sku,
        int $effectivePriceIdr,
        int $floorPriceIdr,
        int $ceilingPriceIdr,
        int $lockMinutes = 30,
        string $channel = 'STORE_B2C'
    ): FrozenQuote {
        $lockedUntil = now()->addMinutes($lockMinutes);

        // Deterministic timelock token based on SKU, price, duration, and target timestamp
        $timelockEntropy = hash('sha256', "{$sku}:{$effectivePriceIdr}:{$lockMinutes}:{$lockedUntil->timestamp}");
        $quoteCode = 'QT-'.strtoupper(substr($timelockEntropy, 0, 12));
        $quoteHash = hash('sha256', "{$quoteCode}:{$sku}:{$effectivePriceIdr}:{$lockedUntil->toIso8601String()}");

        return FrozenQuote::create([
            'quote_code' => $quoteCode,
            'sku' => $sku,
            'frozen_price_idr' => $effectivePriceIdr,
            'floor_price_idr' => $floorPriceIdr,
            'ceiling_price_idr' => $ceilingPriceIdr,
            'quote_hash' => $quoteHash,
            'channel' => $channel,
            'locked_until' => $lockedUntil,
            'status' => 'LOCKED',
        ]);
    }

    /**
     * 81.4 Convert immutable frozen quote to order document with integrity verification
     */
    public function convertFrozenQuoteToOrder(string $quoteCode, string $orderRef): FrozenQuote
    {
        $quote = FrozenQuote::where('quote_code', $quoteCode)->first();
        if (! $quote) {
            throw new InvalidArgumentException("Frozen quote {$quoteCode} not found.");
        }

        $expectedHash = hash('sha256', "{$quote->quote_code}:{$quote->sku}:{$quote->frozen_price_idr}:{$quote->locked_until->toIso8601String()}");
        if ($quote->quote_hash !== $expectedHash) {
            throw new RuntimeException("Quote hash integrity verification failed for {$quoteCode}.");
        }

        if ($quote->status === 'CONVERTED') {
            return $quote;
        }

        if ($quote->status === 'EXPIRED' || now()->greaterThan($quote->locked_until)) {
            $quote->update(['status' => 'EXPIRED']);
            throw new DomainException("Frozen quote {$quoteCode} has expired and cannot be converted to order.");
        }

        $quote->update([
            'order_ref' => $orderRef,
            'converted_at' => now(),
            'status' => 'CONVERTED',
        ]);

        return $quote->fresh();
    }

    /**
     * 81.5 Channel-aware dynamic quoting
     */
    public function quoteForChannel(
        string $channel,
        string $sku,
        int $baseHppIdr,
        int $hetCeilingIdr,
        float $demandIndex,
        ?int $contractPriceIdr = null
    ): array {
        // Adjust demand sensitivity or volume considerations per channel
        $adjustedIndex = match ($channel) {
            'DISTRIBUTOR_WHOLESALE' => min(1.10, $demandIndex * 0.90),
            'EXPORT' => $demandIndex * 1.05,
            'VENDING' => max(1.00, $demandIndex * 1.15),
            default => $demandIndex,
        };

        return $this->computeTickPrice(
            sku: $sku,
            baseHppIdr: $baseHppIdr,
            hetCeilingIdr: $hetCeilingIdr,
            demandIndex: $adjustedIndex,
            contractPriceIdr: $contractPriceIdr,
            channel: $channel
        );
    }

    /**
     * 81.6 Real-time analytics summary
     */
    public function getAnalyticsSummary(string $sku, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tickQuery = PriceTick::where('sku', $sku);
        $waterfallQuery = PriceWaterfallLog::where('sku', $sku);

        if ($from !== null) {
            $tickQuery->where('tick_time', '>=', $from);
            $waterfallQuery->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $tickQuery->where('tick_time', '<=', $to);
            $waterfallQuery->where('created_at', '<=', $to);
        }

        $totalTicks = $tickQuery->count();
        $minPrice = (int) ($tickQuery->min('price_idr') ?? 0);
        $maxPrice = (int) ($tickQuery->max('price_idr') ?? 0);
        $avgPrice = (int) round((float) ($tickQuery->avg('price_idr') ?? 0));

        $guardrailViolations = FrozenQuote::where('sku', $sku)
            ->where(function ($q) {
                $q->whereColumn('frozen_price_idr', '<', 'floor_price_idr')
                    ->orWhereColumn('frozen_price_idr', '>', 'ceiling_price_idr');
            })
            ->count();

        $convertedQuotes = FrozenQuote::where('sku', $sku)->where('status', 'CONVERTED')->count();
        $lockedQuotes = FrozenQuote::where('sku', $sku)->where('status', 'LOCKED')->count();

        return [
            'sku' => $sku,
            'total_ticks' => $totalTicks,
            'min_price_idr' => $minPrice,
            'max_price_idr' => $maxPrice,
            'avg_price_idr' => $avgPrice,
            'guardrail_violations' => $guardrailViolations,
            'quotes_converted_count' => $convertedQuotes,
            'quotes_locked_count' => $lockedQuotes,
        ];
    }
}
