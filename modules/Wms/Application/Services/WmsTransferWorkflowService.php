<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\ProcessCrossDockingTransferAction;
use Modules\Wms\Domain\ValueObjects\TransferManifest;

/**
 * Orkestrasi eksekusi manifest transfer antar-gudang.
 */
final class WmsTransferWorkflowService
{
    public function __construct(
        private readonly ProcessCrossDockingTransferAction $transferAction,
    ) {}

    /**
     * @param  array<int, array{from_bin_id: int, to_bin_id: int, received_qty_base: int}>  $binMappingByProduct
     * @return array{
     *     transfer_number: string,
     *     line_count: int,
     *     total_shipped_value_idr: int,
     *     total_received_value_idr: int,
     *     total_lost_value_idr: int
     * }
     */
    public function executeManifest(
        int $fromWarehouseId,
        int $toWarehouseId,
        TransferManifest $manifest,
        array $binMappingByProduct,
    ): array {
        $totalShipped = 0;
        $totalReceived = 0;
        $totalLost = 0;

        foreach ($manifest->lines as $idx => $line) {
            $pid = (int) $line['product_id'];
            $map = $binMappingByProduct[$pid] ?? null;
            if ($map === null) {
                continue;
            }

            $res = $this->transferAction->execute(
                fromWarehouseId: $fromWarehouseId,
                fromBinId: (int) $map['from_bin_id'],
                toWarehouseId: $toWarehouseId,
                toBinId: (int) $map['to_bin_id'],
                productId: $pid,
                shippedQtyBase: (int) $line['qty_base'],
                receivedQtyBase: (int) $map['received_qty_base'],
                unitCostIdr: (int) $line['unit_cost_idr'],
                transferRef: "{$manifest->transferNumber}-L{$idx}",
            );

            $totalShipped += $res['shipped_value_idr'];
            $totalReceived += $res['received_value_idr'];
            $totalLost += $res['lost_value_idr'];
        }

        return [
            'transfer_number' => $manifest->transferNumber,
            'line_count' => count($manifest->lines),
            'total_shipped_value_idr' => $totalShipped,
            'total_received_value_idr' => $totalReceived,
            'total_lost_value_idr' => $totalLost,
        ];
    }
}
