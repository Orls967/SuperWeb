<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Domain\Models\ExportReeferShipment;
use Modules\Marine\Domain\StateMachines\ExportShipmentStateMachine;

class DispatchExportReeferAction
{
    protected ExportShipmentStateMachine $stateMachine;

    public function __construct(ExportShipmentStateMachine $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    public function execute(int $shipmentId): ExportReeferShipment
    {
        $shipment = ExportReeferShipment::findOrFail($shipmentId);

        $nextStatus = $this->stateMachine->transition(
            $shipment->shipment_status,
            ExportShipmentStateMachine::STATUS_IN_TRANSIT
        );

        $shipment->update([
            'shipment_status' => $nextStatus,
            'dispatched_at' => now(),
        ]);

        return $shipment;
    }
}

