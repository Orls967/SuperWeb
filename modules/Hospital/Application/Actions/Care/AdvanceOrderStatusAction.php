<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\StateMachines\ClinicalOrderStateMachine;

/**
 * Aksi Pembaruan Status Order Clinical Pathway (Fase 87.4).
 */
class AdvanceOrderStatusAction
{
    public function execute(
        int $orderId,
        string $toStatus,
        int $delayMinutes = 0,
        ?string $note = null,
        ?int $actorId = null
    ): HospitalOrder {
        $order = HospitalOrder::findOrFail($orderId);
        ClinicalOrderStateMachine::transition($order, $toStatus, $delayMinutes, $note, $actorId);

        return $order->fresh();
    }
}
