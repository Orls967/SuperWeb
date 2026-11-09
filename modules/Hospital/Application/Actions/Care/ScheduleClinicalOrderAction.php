<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\StateMachines\ClinicalOrderStateMachine;

/**
 * Aksi Pembuatan Order Clinical Pathway Dokter Berurutan (Fase 87.4).
 */
class ScheduleClinicalOrderAction
{
    /**
     * @param array{
     *     encounter_id: int,
     *     order_type: string,
     *     description: string,
     *     sequence_step: int,
     *     scheduled_at: string,
     *     actor_id?: ?int
     * } $data
     */
    public function execute(array $data): HospitalOrder
    {
        $orderCode = 'ORD-' . date('Ymd') . '-' . substr(hash('sha256', $data['description'] . microtime()), 0, 8);

        $order = HospitalOrder::create([
            'order_code' => strtoupper($orderCode),
            'encounter_id' => $data['encounter_id'],
            'order_type' => strtoupper($data['order_type']),
            'description' => $data['description'],
            'sequence_step' => (int) ($data['sequence_step'] ?? 1),
            'scheduled_at' => $data['scheduled_at'],
            'status' => ClinicalOrderStateMachine::STATUS_SCHEDULED,
            'delay_alert_sent' => false,
        ]);

        return $order;
    }
}
