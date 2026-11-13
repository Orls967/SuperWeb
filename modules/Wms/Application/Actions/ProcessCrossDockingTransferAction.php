<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Transfer;
use Modules\Wms\Domain\Models\Warehouse;

final class ProcessCrossDockingTransferAction extends BaseAction
{
    public function __construct(
        private readonly WmsService $wmsService
    ) {}

    /**
     * Executes cross-docking from inbound dock appointment directly to outbound transfer.
     *
     * @param  list<array{product_id: int, qty: int}>  $items
     */
    public function execute(
        DockAppointment $inboundDock,
        Warehouse $originWarehouse,
        Warehouse $destinationWarehouse,
        array $items,
        ?User $operator = null
    ): Transfer {
        if (empty($items)) {
            throw new InvalidArgumentException('Item transfer cross-dock tidak boleh kosong.');
        }

        if ($inboundDock->status === 'cancelled') {
            throw new InvalidArgumentException('Dock appointment inbound telah dibatalkan.');
        }

        $user = $operator ?? auth()->user() ?? User::first();
        if ($user === null) {
            throw new InvalidArgumentException('Operator cross-docking diperlukan.');
        }

        return $this->transaction(function () use ($inboundDock, $originWarehouse, $destinationWarehouse, $items, $user) {
            // Create transfer with cross-dock reference
            $lines = array_map(fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'qty' => (float) $item['qty'],
            ], $items);

            $transfer = $this->wmsService->createTransfer(
                $originWarehouse,
                $destinationWarehouse,
                $lines,
                $user,
                true // crossDock flag
            );

            // Ship immediately as cross-dock avoids internal bin putaway
            $transfer = $this->wmsService->shipTransfer($transfer, $user);

            $this->audit('wms.cross_dock_processed', $transfer, null, [
                'inbound_dock_id' => $inboundDock->id,
                'from_warehouse_id' => $originWarehouse->id,
                'to_warehouse_id' => $destinationWarehouse->id,
            ]);

            return $transfer;
        });
    }
}
