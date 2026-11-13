<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotWave;

/**
 * Layanan rekonsiliasi konservasi order pada wave robotika (J2): memastikan tidak ada order hilang atau ganda.
 */
final readonly class RoboticsWaveReconciliationService
{
    /**
     * @param  list<string>  $expectedOrderIds
     * @return array{
     *     warehouse_id:string,
     *     batch_code:string,
     *     expected_count:int,
     *     actual_count:int,
     *     missing_order_ids:list<string>,
     *     duplicate_order_ids:list<string>,
     *     fallback_missions_count:int,
     *     conserved:bool
     * }
     */
    public function reconcileBatch(string $warehouseId, string $batchCode, array $expectedOrderIds): array
    {
        $waves = RobotWave::query()
            ->where('warehouse_id', $warehouseId)
            ->where('batch_code', $batchCode)
            ->orderBy('id')
            ->get();

        $seen = [];
        $duplicates = [];
        $actualOrderIds = [];

        foreach ($waves as $wave) {
            $orderIds = is_array($wave->order_ids) ? $wave->order_ids : [];
            foreach ($orderIds as $rawId) {
                $orderId = (string) $rawId;
                $actualOrderIds[] = $orderId;
                if (isset($seen[$orderId])) {
                    $duplicates[] = $orderId;
                }
                $seen[$orderId] = true;
            }
        }

        $missing = [];
        foreach ($expectedOrderIds as $expectedId) {
            if (! isset($seen[(string) $expectedId])) {
                $missing[] = (string) $expectedId;
            }
        }

        $fallbackCount = RobotMission::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', RobotMissionStateMachine::STATUS_FALLBACK_MANUAL)
            ->count();

        sort($missing);
        $uniqueDuplicates = array_values(array_unique($duplicates));
        sort($uniqueDuplicates);

        return [
            'warehouse_id' => $warehouseId,
            'batch_code' => $batchCode,
            'expected_count' => count($expectedOrderIds),
            'actual_count' => count($actualOrderIds),
            'missing_order_ids' => $missing,
            'duplicate_order_ids' => $uniqueDuplicates,
            'fallback_missions_count' => $fallbackCount,
            'conserved' => $missing === [] && $uniqueDuplicates === [],
        ];
    }
}
