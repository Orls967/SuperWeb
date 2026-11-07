<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LayeredProductionHedgeVerifier
{
    /**
     * @param  list<LayeredHedgeHorizonQuote>  $quotes
     */
    public function verify(LayeredProductionHedgeResult $result, array $quotes): bool
    {
        $sumPlanned = 0.0;
        $sumExisting = 0.0;
        foreach ($quotes as $quote) {
            $sumPlanned += $quote->plannedProductionTons;
            $sumExisting += $quote->existingHedgedTons;
        }

        if (abs($result->aggregatePlannedProductionTons - round($sumPlanned, 2)) > 0.05) {
            return false;
        }

        if (abs($result->aggregateExistingHedgedTons - round($sumExisting, 2)) > 0.05) {
            return false;
        }

        if ($result->portfolioCoveragePct < 0.0 || $result->portfolioCoveragePct > 100.0) {
            return false;
        }

        $expectedHash = hash('sha256', sprintf(
            '%.2f:%.2f:%.2f:%.2f:%d',
            $result->aggregatePlannedProductionTons,
            $result->aggregateExistingHedgedTons,
            $result->aggregateNewHedgeRecommendedTons,
            $result->portfolioCoveragePct,
            $result->securedNotionalValueIdr
        ));

        return hash_equals($expectedHash, $result->verificationHash);
    }
}
