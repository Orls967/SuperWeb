<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Shared\Application\BaseAction;

class ReconcileLiquorPourSpoutVarianceAction extends BaseAction
{
    /**
     * Reconcile liquor pour volume from IoT smart spouts vs POS billed shots.
     *
     * @return array{bottle_sku: string, billed_shots: int, expected_dispensed_ml: float, metered_dispensed_ml: float, variance_ml: float, variance_pct: float, is_shrinkage_flagged: bool, status: string}
     */
    public function handle(
        string $bottleSku,
        int $billedShots,
        float $meteredDispensedMl,
        float $standardShotVolumeMl = 45.0
    ): array {
        $expectedDispensedMl = round($billedShots * $standardShotVolumeMl, 2);
        $varianceMl = round($meteredDispensedMl - $expectedDispensedMl, 2);

        $variancePct = $expectedDispensedMl > 0
            ? round((abs($varianceMl) / $expectedDispensedMl) * 100, 2)
            : 0.0;

        // Anomaly / shrinkage flagged if variance exceeds 8.0%
        $isShrinkageFlagged = ($variancePct > 8.0 && $varianceMl > 0);
        $status = $isShrinkageFlagged ? 'SHRINKAGE_ALERT_OVER_POUR' : ($variancePct > 8.0 ? 'UNDER_POUR_DEFICIT' : 'RECONCILED_TOLERANCE_OK');

        $this->audit(
            action: 'venue.bar.liquor_reconciled',
            auditable: null,
            oldValues: null,
            newValues: [
                'bottle_sku' => $bottleSku,
                'billed_shots' => $billedShots,
                'metered_dispensed_ml' => $meteredDispensedMl,
                'variance_ml' => $varianceMl,
                'variance_pct' => $variancePct,
                'status' => $status,
            ],
            correlationId: "ven_spout_{$bottleSku}_".date('YmdHis'),
            impactType: 'bar_spout_variance'
        );

        return [
            'bottle_sku' => $bottleSku,
            'billed_shots' => $billedShots,
            'expected_dispensed_ml' => $expectedDispensedMl,
            'metered_dispensed_ml' => $meteredDispensedMl,
            'variance_ml' => $varianceMl,
            'variance_pct' => $variancePct,
            'is_shrinkage_flagged' => $isShrinkageFlagged,
            'status' => $status,
        ];
    }
}
