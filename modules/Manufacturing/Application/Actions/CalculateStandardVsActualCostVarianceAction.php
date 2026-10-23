<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\Variance;
use Modules\Shared\Application\BaseAction;

final class CalculateStandardVsActualCostVarianceAction extends BaseAction
{
    /**
     * Compares standard costs against actual consumptions and generates cost variance records.
     *
     * @param  array{
     *     standard_material_cost_idr: int,
     *     actual_material_cost_idr: int,
     *     standard_labor_cost_idr: int,
     *     actual_labor_cost_idr: int,
     *     standard_overhead_cost_idr: int,
     *     actual_overhead_cost_idr: int
     * } $costComparison
     * @return array{
     *     material_usage_variance_idr: int,
     *     labor_efficiency_variance_idr: int,
     *     overhead_variance_idr: int,
     *     total_net_variance_idr: int,
     *     records: list<Variance>
     * }
     */
    public function execute(ProductionOrder $order, array $costComparison): array
    {
        $stdMat = $costComparison['standard_material_cost_idr'] ?? 0;
        $actMat = $costComparison['actual_material_cost_idr'] ?? 0;
        $stdLab = $costComparison['standard_labor_cost_idr'] ?? 0;
        $actLab = $costComparison['actual_labor_cost_idr'] ?? 0;
        $stdOvh = $costComparison['standard_overhead_cost_idr'] ?? 0;
        $actOvh = $costComparison['actual_overhead_cost_idr'] ?? 0;

        if ($stdMat < 0 || $actMat < 0 || $stdLab < 0 || $actLab < 0) {
            throw new InvalidArgumentException('Nilai biaya perbandingan standar vs aktual tidak boleh negatif.');
        }

        return $this->transaction(function () use ($order, $stdMat, $actMat, $stdLab, $actLab, $stdOvh, $actOvh) {
            // Unfavourable variance is positive, favourable variance is negative
            $matVariance = $actMat - $stdMat;
            $labVariance = $actLab - $stdLab;
            $ovhVariance = $actOvh - $stdOvh;
            $totalNetVariance = $matVariance + $labVariance + $ovhVariance;

            $records = [];

            if ($matVariance !== 0) {
                $records[] = Variance::create([
                    'order_id' => $order->id,
                    'kind' => 'usage',
                    'amount_idr' => $matVariance,
                    'policy' => 'expensed_to_cogs',
                    'posted' => false,
                    'notes' => 'Material usage variance vs BOM standard cost',
                ]);
            }

            if ($labVariance !== 0) {
                $records[] = Variance::create([
                    'order_id' => $order->id,
                    'kind' => 'labor_efficiency',
                    'amount_idr' => $labVariance,
                    'policy' => 'expensed_to_cogs',
                    'posted' => false,
                    'notes' => 'Labor efficiency variance vs routing standard',
                ]);
            }

            if ($ovhVariance !== 0) {
                $records[] = Variance::create([
                    'order_id' => $order->id,
                    'kind' => 'overhead_volume',
                    'amount_idr' => $ovhVariance,
                    'policy' => 'expensed_to_cogs',
                    'posted' => false,
                    'notes' => 'Overhead volume variance vs work center allocation',
                ]);
            }

            $this->audit('mfg.cost_variance_calculated', $order, null, [
                'order_number' => $order->number,
                'total_net_variance_idr' => $totalNetVariance,
                'variance_count' => count($records),
            ]);

            return [
                'material_usage_variance_idr' => $matVariance,
                'labor_efficiency_variance_idr' => $labVariance,
                'overhead_variance_idr' => $ovhVariance,
                'total_net_variance_idr' => $totalNetVariance,
                'records' => $records,
            ];
        });
    }
}
