<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\ClinicalOrderTransition;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class EscalateUnresolvedClinicalOrderAction
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * Memindai order klinis (lab/farmasi/radiologi) yang mengalami keterlambatan SLA
     * dan menaikkan eskalasi ke Duty Medical Officer (DMO) / Kepala Ruangan.
     *
     * @return array<string, mixed>
     */
    public function execute(int $orderId, string $reason, int $actorId): array
    {
        $order = DB::table('hsp_clinical_orders')->where('id', $orderId)->first();
        if (! $order) {
            return ['success' => false, 'message' => "Order #{$orderId} tidak ditemukan."];
        }

        $now = CarbonImmutable::now()->toIso8601String();

        DB::table('hsp_clinical_orders')->where('id', $orderId)->update([
            'is_escalated' => true,
            'escalation_reason' => $reason,
            'escalated_at' => $now,
            'updated_at' => $now,
        ]);

        ClinicalOrderTransition::create([
            'order_id' => $orderId,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'action' => 'ESCALATE_SLA_BREACH',
            'reason' => $reason,
            'actor_id' => $actorId,
            'payload' => ['escalated_at' => $now],
        ]);

        $this->passportService->appendEvent(
            patientId: $order->patient_id,
            eventType: 'CLINICAL_ORDER_ESCALATED',
            eventPayload: [
                'order_id' => $orderId,
                'order_type' => $order->order_type,
                'item_name' => $order->item_name,
                'reason' => $reason,
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'order_id' => $orderId,
            'escalation_reason' => $reason,
            'escalated_at' => $now,
        ];
    }
}
