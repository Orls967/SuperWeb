<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\PostCycleCountAdjustmentAction;
use Modules\Wms\Domain\Algorithms\CycleCountScheduler;
use Modules\Wms\Domain\Support\QuantityNormalizer;
use Modules\Wms\Domain\ValueObjects\CycleCountPolicy;

/**
 * Orkestrasi alur cycle count: penjadwalan H3, evaluasi kebijakan selisih, dan eksekusi penyesuaian.
 */
final class WmsCycleCountWorkflowService
{
    public function __construct(
        private readonly CycleCountScheduler $scheduler,
        private readonly PostCycleCountAdjustmentAction $adjustmentAction,
        private readonly CycleCountPolicy $policy = new CycleCountPolicy,
    ) {}

    /**
     * @param  list<array{id: string|int, abcClass: string, lastCountedDay: int}>  $candidates
     * @return array{selected_count: int, carried_over_count: int, selected: list<array<string, mixed>>}
     */
    public function planDailyBatch(int $today, array $candidates): array
    {
        $result = $this->scheduler->schedule($today, $candidates, $this->policy->dailyBinCapacity);

        return [
            'selected_count' => count($result['selected']),
            'carried_over_count' => count($result['carried_over']),
            'selected' => $result['selected'],
        ];
    }

    /**
     * @return array{
     *     requires_approval: bool,
     *     variance_bps: int,
     *     result: ?array<string, mixed>
     * }
     */
    public function submitBinCount(
        int $warehouseId,
        int $binId,
        int $productId,
        int $systemQtyBase,
        int $countedQtyBase,
        int $unitCostIdr,
        string $cycleCountRef,
        ?int $supervisorUserId = null,
    ): array {
        $varianceBps = QuantityNormalizer::varianceBps(max(1, $systemQtyBase), $countedQtyBase);
        $absValue = abs($countedQtyBase - $systemQtyBase) * $unitCostIdr;
        $needsApproval = $this->policy->requiresSupervisorApproval($varianceBps, $absValue);

        if ($needsApproval && $supervisorUserId === null) {
            return [
                'requires_approval' => true,
                'variance_bps' => $varianceBps,
                'result' => null,
            ];
        }

        $res = $this->adjustmentAction->execute(
            warehouseId: $warehouseId,
            binId: $binId,
            productId: $productId,
            countedQtyBase: $countedQtyBase,
            unitCostIdr: $unitCostIdr,
            cycleCountRef: $cycleCountRef,
        );

        return [
            'requires_approval' => false,
            'variance_bps' => $varianceBps,
            'result' => $res,
        ];
    }
}
