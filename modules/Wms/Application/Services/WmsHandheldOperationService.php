<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use DomainException;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\StateMachines\WarehouseTaskStateMachine;

/**
 * Layanan eksekusi tugas handheld operator (validasi scan barcode bin/SKU & konfirmasi tugas).
 */
final class WmsHandheldOperationService
{
    public function __construct(
        private readonly WarehouseTaskStateMachine $stateMachine,
    ) {}

    public function confirmScanAndComplete(
        string|int $taskId,
        string $scannedBinCode,
        int $scannedProductId,
        int $operatorUserId,
    ): Task {
        $task = Task::findOrFail($taskId);

        $expectedBinId = $task->kind === 'putaway' ? $task->to_bin_id : $task->from_bin_id;
        if ($expectedBinId !== null) {
            $bin = Bin::findOrFail($expectedBinId);
            if (strtoupper(trim((string) $bin->code)) !== strtoupper(trim($scannedBinCode))) {
                throw new DomainException("Kode bin hasil scan '{$scannedBinCode}' tidak cocok dengan '{$bin->code}'.");
            }
        }

        if ((int) $task->product_id !== $scannedProductId) {
            throw new DomainException("Produk hasil scan #{$scannedProductId} tidak cocok dengan tugas #{$task->product_id}.");
        }

        if ($task->status === WarehouseTaskStateMachine::STATUS_CREATED) {
            $this->stateMachine->transition($task, 'assign', $operatorUserId);
        }
        if ($task->status === WarehouseTaskStateMachine::STATUS_ASSIGNED) {
            $this->stateMachine->transition($task, 'start', $operatorUserId);
        }
        if ($task->status === WarehouseTaskStateMachine::STATUS_IN_PROGRESS) {
            $this->stateMachine->transition($task, 'confirm', $operatorUserId, "Handheld scan {$scannedBinCode}");
        }
        if ($task->status === WarehouseTaskStateMachine::STATUS_CONFIRMED) {
            $this->stateMachine->transition($task, 'close', $operatorUserId);
        }

        return $task->fresh() ?? $task;
    }
}
