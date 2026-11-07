<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LmeMetalMarkToMarketVerifier
{
    public function verify(LmeMetalMarkToMarketResult $result, LmeMarketQuote $quote): bool
    {
        if ($result->metricTons <= 0.0) {
            return false;
        }

        // Expected USD PnL verification
        $priceDelta = $result->currentMarketPriceUsdCentsPerTon - $result->entryPriceUsdCentsPerTon;
        $signMultiplier = ($result->positionType === 'SHORT') ? -1 : 1;
        $expectedPnlUsdCents = (int) round($signMultiplier * $priceDelta * $result->metricTons);

        if (abs($result->unrealizedPnlUsdCents - $expectedPnlUsdCents) > 10) {
            return false;
        }

        // Expected IDR PnL verification
        $expectedPnlIdr = (int) round(($result->unrealizedPnlUsdCents / 100.0) * $quote->exchangeRateIdrPerUsd);
        if (abs($result->unrealizedPnlIdr - $expectedPnlIdr) > 500) {
            return false;
        }

        // Margin call verification: triggered if unrealized loss exceeds initial margin - maintenance margin
        if ($result->unrealizedPnlIdr < 0) {
            $lossIdr = abs($result->unrealizedPnlIdr);
            $marginBuffer = $result->initialMarginRequirementIdr - $result->maintenanceMarginThresholdIdr;
            $shouldTrigger = $lossIdr > $marginBuffer;
            if ($shouldTrigger !== $result->isMarginCallTriggered) {
                return false;
            }
        } elseif ($result->isMarginCallTriggered) {
            return false;
        }

        $expectedHash = hash('sha256', sprintf(
            '%s:%.2f:%d:%d:%d:%d:%s',
            $result->positionType,
            $result->metricTons,
            $result->currentMarketPriceUsdCentsPerTon,
            $result->unrealizedPnlUsdCents,
            $result->unrealizedPnlIdr,
            $result->variationMarginRequiredIdr,
            $result->isMarginCallTriggered ? '1' : '0'
        ));

        return hash_equals($expectedHash, $result->verificationHash);
    }
}
