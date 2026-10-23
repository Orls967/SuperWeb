<?php

declare(strict_types=1);

namespace Modules\International\Application\Actions;

use InvalidArgumentException;
use Modules\International\Domain\Models\OemContract;

class ReconcileOemToolingAmortizationAction
{
    /**
     * @return array{
     *     contract_number: string,
     *     units_produced: int,
     *     tolling_fee_unit_idr: int,
     *     total_tolling_fee_idr: int,
     *     tooling_amortization_per_unit_idr: int,
     *     total_tooling_amortized_idr: int,
     *     total_payable_idr: int,
     *     remaining_unamortized_tooling_idr: int
     * }
     */
    public function execute(
        OemContract $contract,
        int $unitsProduced,
        int $toolingTotalCostIdr,
        int $totalContractExpectedUnits
    ): array {
        if ($unitsProduced <= 0) {
            throw new InvalidArgumentException('Units produced must be greater than zero.');
        }

        if ($totalContractExpectedUnits <= 0) {
            throw new InvalidArgumentException('Total contract expected units must be greater than zero.');
        }

        if ($toolingTotalCostIdr < 0) {
            throw new InvalidArgumentException('Tooling total cost cannot be negative.');
        }

        $tollingFeePerUnit = $contract->tolling_fee_per_unit_idr;
        $totalTollingFee = $unitsProduced * $tollingFeePerUnit;

        $amortizationPerUnit = (int) round($toolingTotalCostIdr / $totalContractExpectedUnits);
        $totalToolingAmortized = min($toolingTotalCostIdr, $amortizationPerUnit * $unitsProduced);
        $remainingTooling = max(0, $toolingTotalCostIdr - $totalToolingAmortized);

        $totalPayable = $totalTollingFee + $totalToolingAmortized;

        return [
            'contract_number' => $contract->contract_number,
            'units_produced' => $unitsProduced,
            'tolling_fee_unit_idr' => $tollingFeePerUnit,
            'total_tolling_fee_idr' => $totalTollingFee,
            'tooling_amortization_per_unit_idr' => $amortizationPerUnit,
            'total_tooling_amortized_idr' => $totalToolingAmortized,
            'total_payable_idr' => $totalPayable,
            'remaining_unamortized_tooling_idr' => $remainingTooling,
        ];
    }
}
