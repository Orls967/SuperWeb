<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Inventory;

class ReorderPolicy
{
    /**
     * D82-1 Evaluasi kebijakan persediaan VMI (s, S).
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
    public function evaluate(array $input): ReorderPolicyResult
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

        // SS = ceil(z * sigma * sqrt(L))
        $ss = (int) ceil($z * $sigma * sqrt($leadTime));

        // ROP = d_mean * L + SS
        $rop = (int) round($dMean * $leadTime) + $ss;

        // S = ROP + d_mean * review
        $s = $rop + (int) round($dMean * $review);

        // posisi = on_hand + on_order - backorder
        $posisi = $onHand + $onOrder - $backorder;

        // qty = S - posisi, dibulatkan ke atas ke kelipatan pack
        $rawQty = max(0, $s - $posisi);
        $orderQty = (int) (ceil($rawQty / $packSize) * $packSize);

        $totalCostIdr = $orderQty * $unitPriceIdr;
        $autoPoAllowed = ($totalCostIdr <= $plafonIdr);
        $requiresApproval = ! $autoPoAllowed;

        $certificate = hash('sha256', implode('|', [
            $ss,
            $rop,
            $s,
            $posisi,
            $orderQty,
            $totalCostIdr,
            $autoPoAllowed ? '1' : '0',
        ]));

        $result = new ReorderPolicyResult(
            safetyStock: $ss,
            reorderPoint: $rop,
            orderUpToLevel: $s,
            netPosition: $posisi,
            rawOrderQty: $rawQty,
            orderQty: $orderQty,
            totalCostIdr: $totalCostIdr,
            autoPoAllowed: $autoPoAllowed,
            requiresApproval: $requiresApproval,
            certificate: $certificate
        );

        // Verifikasi sertifikat internal
        ReorderPolicyCertificateVerifier::verify($input, $result);

        return $result;
    }
}
