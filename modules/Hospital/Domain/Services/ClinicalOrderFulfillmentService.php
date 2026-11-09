<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\ClinicalOrderTransition;
use Modules\Hospital\Domain\Services\PatientPassportService;
use Modules\Hospital\Domain\StateMachines\ClinicalOrderStateMachine;

final class ClinicalOrderFulfillmentService
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * Memperbarui pemenuhan order klinis laboratorium/radiologi/farmasi
     * beserta hasil numerik dan bukti otentikasi pelaksana.
     *
     * @param int $orderId
     * @param array<string, mixed> $resultPayload
     * @param int $actorId
     * @return array<string, mixed>
     */
    public function fulfillOrder(int $orderId, array $resultPayload, int $actorId): array
    {
        $order = DB::table('hsp_clinical_orders')->where('id', $orderId)->first();
        if (!$order) {
            return ['success' => false, 'message' => "Order #{$orderId} tidak ditemukan."];
        }

        $now = CarbonImmutable::now()->toIso8601String();

        DB::table('hsp_clinical_orders')->where('id', $orderId)->update([
            'status' => ClinicalOrderStateMachine::STATUS_COMPLETED,
            'results_payload' => json_encode($resultPayload, JSON_UNESCAPED_SLASHES),
            'completed_at' => $now,
            'updated_at' => $now,
        ]);

        ClinicalOrderTransition::create([
            'order_id' => $orderId,
            'from_status' => $order->status,
            'to_status' => ClinicalOrderStateMachine::STATUS_COMPLETED,
            'action' => 'FULFILL_ORDER_RESULTS',
            'reason' => 'Hasil pemeriksaan penunjang terverifikasi petugas penunjang medis',
            'actor_id' => $actorId,
            'payload' => $resultPayload,
        ]);

        $this->passportService->appendEvent(
            patientId: $order->patient_id,
            eventType: 'CLINICAL_ORDER_COMPLETED',
            eventPayload: [
                'order_id' => $orderId,
                'order_type' => $order->order_type,
                'item_name' => $order->item_name,
                'results' => $resultPayload,
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'order_id' => $orderId,
            'status' => ClinicalOrderStateMachine::STATUS_COMPLETED,
            'completed_at' => $now,
        ];
    }
}
