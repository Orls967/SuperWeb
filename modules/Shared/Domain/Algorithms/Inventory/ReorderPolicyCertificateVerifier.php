<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Inventory;

use InvalidArgumentException;

final class ReorderPolicyCertificateVerifier
{
    /**
     * Memverifikasi sertifikat deterministik kebijakan persediaan (s, S).
     *
     * @param array{
     *     d_mean: int|float,
     *     sigma: int|float,
     *     lead_time_days: int,
     *     z: float,
     *     review_period_days: int,
     *     on_hand: int,
     *     on_order: int,
     *     backorder: int,
     *     pack_size: int,
     *     unit_price_idr: int,
     *     contract_ceiling_idr: int,
     * } $input
     */
    public static function verify(array $input, ReorderPolicyResult $result): bool
    {
        $dMean = (float) $input['d_mean'];
        $sigma = (float) $input['sigma'];
        $leadTime = (int) $input['lead_time_days'];
        $z = (float) $input['z'];
        $review = (int) $input['review_period_days'];
        $onHand = (int) $input['on_hand'];
        $onOrder = (int) $input['on_order'];
        $backorder = (int) $input['backorder'];
        $packSize = max(1, (int) ($input['pack_size'] ?? 1));
        $unitPriceIdr = (int) $input['unit_price_idr'];
        $plafonIdr = (int) $input['contract_ceiling_idr'];

        $expectedSs = (int) ceil($z * $sigma * sqrt($leadTime));
        $expectedRop = (int) round($dMean * $leadTime) + $expectedSs;
        $expectedS = $expectedRop + (int) round($dMean * $review);

        $expectedPosisi = $onHand + $onOrder - $backorder;
        $expectedRawQty = max(0, $expectedS - $expectedPosisi);
        $expectedQty = (int) (ceil($expectedRawQty / $packSize) * $packSize);
        $expectedTotalIdr = $expectedQty * $unitPriceIdr;

        $expectedAutoPo = ($expectedTotalIdr <= $plafonIdr);

        if ($result->safetyStock !== $expectedSs
            || $result->reorderPoint !== $expectedRop
            || $result->orderUpToLevel !== $expectedS
            || $result->netPosition !== $expectedPosisi
            || $result->rawOrderQty !== $expectedRawQty
            || $result->orderQty !== $expectedQty
            || $result->totalCostIdr !== $expectedTotalIdr
            || $result->autoPoAllowed !== $expectedAutoPo) {
            throw new InvalidArgumentException('Sertifikat kebijakan persediaan tidak valid: inkonsistensi nilai turunan.');
        }

        $expectedHash = hash('sha256', implode('|', [
            $expectedSs,
            $expectedRop,
            $expectedS,
            $expectedPosisi,
            $expectedQty,
            $expectedTotalIdr,
            $expectedAutoPo ? '1' : '0',
        ]));

        if ($result->certificate !== $expectedHash) {
            throw new InvalidArgumentException('Sertifikat kebijakan persediaan rusak: hash signature mismatch.');
        }

        return true;
    }
}
