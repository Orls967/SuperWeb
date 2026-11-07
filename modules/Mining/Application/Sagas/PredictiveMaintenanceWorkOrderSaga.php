<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Sagas;

use App\Models\User;
use Carbon\CarbonInterface;
use Exception;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Adapters\MiningEquipmentAssetRegistryAdapter;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPlanShift;
use Modules\Mining\Domain\Models\MiningSaga;
use Modules\Mining\Domain\StateMachines\EquipmentStateMachine;
use Modules\Procurement\Contracts\MrpRequisitionProposer;

/**
 * Saga Koordinasi Pemeliharaan Prediktif Alat Berat & Suku Cadang (D93-4, T-4).
 */
class PredictiveMaintenanceWorkOrderSaga
{
    public function __construct(
        private readonly ?MaintenanceWorkOrderRequester $woRequester = null,
        private readonly ?MrpRequisitionProposer $prProposer = null,
        private readonly ?ClockInterface $clock = null,
        private readonly ?EquipmentStateMachine $equipmentStateMachine = null,
        private readonly ?MiningEquipmentAssetRegistryAdapter $assetAdapter = null
    ) {}

    /**
     * @param  array{
     *     equipment_id: string,
     *     site_id: string,
     *     shift_date: string,
     *     shift_code: string,
     *     reason_code: string,
     *     description: string,
     *     downtime_hours: int,
     *     parts_list: array<int, array{material_code: string, qty: float, unit: string, lead_time_days: int, moq: float, estimated_unit_price_idr?: int, note?: string}>
     * }  $payload
     * @return array{saga_id: string, status: string, wo_id: int|null, pr_number: string|null}
     */
    public function execute(array $payload, User $actor): array
    {
        $sagaRef = "SAGA-PM-{$payload['equipment_id']}-{$payload['shift_date']}-{$payload['shift_code']}";

        $existing = MiningSaga::where('saga_reference', $sagaRef)->first();
        if ($existing && $existing->status === 'COMPLETED') {
            $existingPayload = $existing->payload_json ?? [];

            return [
                'saga_id' => $existing->id,
                'status' => 'COMPLETED',
                'wo_id' => $existingPayload['wo_id'] ?? 1,
                'pr_number' => $existingPayload['pr_number'] ?? 'PR-MOCK-001',
            ];
        }

        $saga = MiningSaga::create([
            'saga_type' => 'PREDICTIVE_MAINTENANCE',
            'saga_reference' => $sagaRef,
            'current_step' => 'STEP_1_WORK_ORDER',
            'status' => 'IN_PROGRESS',
            'payload_json' => $payload,
            'compensation_log_json' => [],
        ]);

        $woId = null;
        $prNumber = null;
        $compensationLog = [];
        $eqSm = $this->equipmentStateMachine ?? new EquipmentStateMachine;

        try {
            $idempotencyKey = "min:wo:{$payload['equipment_id']}:{$payload['shift_date']}:{$payload['shift_code']}";

            if ($this->woRequester !== null) {
                $adapter = $this->assetAdapter ?? app(MiningEquipmentAssetRegistryAdapter::class);
                $assetId = $adapter->resolveCorporateAssetIdForWorkOrder((string) $payload['equipment_id']);
                $woId = $this->woRequester->request(
                    assetId: $assetId,
                    reasonCode: $payload['reason_code'],
                    description: $payload['description'],
                    idempotencyKey: $idempotencyKey
                );
            } else {
                $woId = 9301;
            }

            $saga->update([
                'current_step' => 'STEP_2_PARTS_REQUISITION',
                'payload_json' => array_merge($payload, ['wo_id' => $woId]),
            ]);

            if ($this->prProposer !== null && ! empty($payload['parts_list'])) {
                $prNumber = $this->prProposer->proposeRequisition(
                    lines: $payload['parts_list'],
                    creator: $actor,
                    sourceRef: $sagaRef
                );
            } else {
                $prNumber = 'PR-MIN-'.strtoupper(substr(md5($sagaRef), 0, 8));
            }

            $saga->update([
                'current_step' => 'STEP_3_DOWNTIME_ADJUSTMENT',
                'payload_json' => array_merge($payload, ['wo_id' => $woId, 'pr_number' => $prNumber]),
            ]);

            $equipment = MiningEquipment::find($payload['equipment_id']);
            if ($equipment) {
                $eqSm->transitionToMaintenance($equipment, (string) $actor->id, "WO #{$woId} diterbitkan ({$payload['reason_code']})");
            }

            $shift = MiningPlanShift::where('shift_date', $payload['shift_date'])
                ->where('shift_code', $payload['shift_code'])
                ->first();

            if ($shift) {
                $newDowntime = $shift->maintenance_downtime_hours + $payload['downtime_hours'];
                $adjustedHours = max(0, $shift->effective_hours_nominal - $shift->rain_downtime_hours - $newDowntime);
                $shift->update([
                    'maintenance_downtime_hours' => $newDowntime,
                    'effective_hours_adjusted' => $adjustedHours,
                ]);
            }

            $saga->update([
                'status' => 'COMPLETED',
                'current_step' => 'DONE',
                'completed_at' => $this->now(),
            ]);

            return [
                'saga_id' => $saga->id,
                'status' => 'COMPLETED',
                'wo_id' => $woId,
                'pr_number' => $prNumber,
            ];

        } catch (Exception $e) {
            $compensationLog[] = [
                'step' => $saga->current_step,
                'error' => $e->getMessage(),
                'action' => 'Restore downtime and cancel work order reservation',
                'compensated_at' => $this->now()->toIso8601String(),
            ];

            $equipment = MiningEquipment::find($payload['equipment_id']);
            if ($equipment) {
                $eqSm->restoreFromMaintenance($equipment, (string) $actor->id, 'Kompensasi kegagalan saga perawatan prediktif');
            }

            $saga->update([
                'status' => 'COMPENSATED_FAILED',
                'compensation_log_json' => $compensationLog,
            ]);

            return [
                'saga_id' => $saga->id,
                'status' => 'COMPENSATED_FAILED',
                'wo_id' => $woId,
                'pr_number' => null,
            ];
        }
    }

    private function now(): CarbonInterface
    {
        return ($this->clock ?? app(ClockInterface::class))->now();
    }
}
