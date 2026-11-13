<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\AutomationPlanCertificate;

/**
 * J1 AutomationRoiPlanner: menghitung ROI (basis points) dan payback (bulan)
 * secara deterministik tanpa aritmetika float.
 */
final class AutomationRoiPlanner
{
    /**
     * @return array{
     *     roi_bps: int,
     *     payback_months: int,
     *     recommended_stage: string,
     *     certificate: AutomationPlanCertificate
     * }
     */
    public function plan(
        string $warehouseId,
        int $capexIdr,
        int $annualSavingsIdr,
        int $targetLinesPerHour,
        string $requestedStage = 'phase_2_amr',
    ): array {
        if ($capexIdr <= 0) {
            throw new InvalidArgumentException('Capex harus lebih besar dari 0 IDR.');
        }
        if ($annualSavingsIdr < 0) {
            throw new InvalidArgumentException('Penghematan tahunan tidak boleh negatif.');
        }

        $roiBps = intdiv($annualSavingsIdr * 10000, $capexIdr);
        $monthlySavings = intdiv($annualSavingsIdr, 12);
        $paybackMonths = $monthlySavings > 0
            ? (int) intdiv($capexIdr + $monthlySavings - 1, $monthlySavings)
            : 999;

        $recommendedStage = $targetLinesPerHour >= 600 && $roiBps >= 3000
            ? 'phase_3_asrs_conveyor'
            : $requestedStage;

        $hash = AutomationPlanCertificate::computeHash(
            $warehouseId,
            $capexIdr,
            $annualSavingsIdr,
            $roiBps,
            $paybackMonths,
        );

        return [
            'roi_bps' => $roiBps,
            'payback_months' => $paybackMonths,
            'recommended_stage' => $recommendedStage,
            'certificate' => new AutomationPlanCertificate(
                warehouseId: $warehouseId,
                capexIdr: $capexIdr,
                annualSavingsIdr: $annualSavingsIdr,
                roiBps: $roiBps,
                paybackMonths: $paybackMonths,
                hash: $hash,
            ),
        ];
    }
}

