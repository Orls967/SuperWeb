<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class WarehouseReceiptDigitalCollateralHaircutVerifier
{
    private const MIN_HAIRCUT_PCT = 10.0;

    private const MAX_HAIRCUT_PCT = 50.0;

    public function verify(WarehouseReceiptDigitalCollateralHaircutResult $result): bool
    {
        if ($result->certifiedWeightMetricTons <= 0.0) {
            return false;
        }

        if ($result->totalHaircutPct < self::MIN_HAIRCUT_PCT || $result->totalHaircutPct > self::MAX_HAIRCUT_PCT) {
            return false;
        }

        $expectedLtvPct = 100.0 - $result->totalHaircutPct;
        if (abs($result->effectiveLtvPct - $expectedLtvPct) > 0.01) {
            return false;
        }

        $expectedGross = (int) round($result->certifiedWeightMetricTons * $result->unitMarketPriceIdrPerTon);
        if (abs($result->grossCollateralValueIdr - $expectedGross) > 100) {
            return false;
        }

        $expectedNet = (int) round($result->grossCollateralValueIdr * (1.0 - ($result->totalHaircutPct / 100.0)));
        if (abs($result->netCollateralValueIdr - $expectedNet) > 100) {
            return false;
        }

        if ($result->maxFinancingCreditLimitIdr > $result->netCollateralValueIdr) {
            return false;
        }

        $expectedSeal = hash('sha256', sprintf(
            '%.3f:%d:%d:%.2f:%d:%d',
            $result->certifiedWeightMetricTons,
            $result->unitMarketPriceIdrPerTon,
            $result->grossCollateralValueIdr,
            $result->totalHaircutPct,
            $result->maxFinancingCreditLimitIdr,
            $result->liquidationThresholdIdr
        ));

        return hash_equals($expectedSeal, $result->digitalSealSignature);
    }
}
