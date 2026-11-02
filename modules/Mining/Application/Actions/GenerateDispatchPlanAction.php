<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Algorithms\AssignmentCertificateVerifier;
use Modules\Mining\Domain\Models\MiningDispatchAssignment;
use Modules\Mining\Domain\Models\MiningDispatchPlan;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningShovelSlot;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;

class GenerateDispatchPlanAction extends BaseAction
{
    public function __construct(
        private readonly HungarianSolver $solver,
        private readonly AssignmentCertificateVerifier $verifier
    ) {}

    /**
     * @param  list<string>  $truckIds
     * @param  list<string>  $shovelSlotIds
     * @param  array<string, array<string, int>>  $costMatrix
     * @param  array<string, string>  $driverMapping
     * @return object{plan: MiningDispatchPlan, assignments: list<MiningDispatchAssignment>}
     */
    public function handle(
        string $siteId,
        string $shiftCode,
        array $truckIds,
        array $shovelSlotIds,
        array $costMatrix,
        string $idempotencyKey = '',
        array $driverMapping = []
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        // Idempotency check
        $existingPlan = MiningDispatchPlan::where('site_id', $siteId)
            ->where('shift_code', $shiftCode)
            ->where('plan_uuid', $idempotencyKey)
            ->first();

        if ($existingPlan) {
            return (object) [
                'plan' => $existingPlan,
                'assignments' => $existingPlan->assignments()->get()->all(),
            ];
        }

        return DB::transaction(function () use (
            $siteId,
            $shiftCode,
            $truckIds,
            $shovelSlotIds,
            $costMatrix,
            $idempotencyKey,
            $driverMapping
        ) {
            // 1. Validate trucks
            $trucks = MiningEquipment::whereIn('id', $truckIds)->get()->keyBy('id');
            foreach ($truckIds as $tId) {
                $truck = $trucks->get($tId);
                if (! $truck || $truck->status === 'GROUNDED') {
                    throw new \DomainException("Truk {$tId} berstatus GROUNDED atau tidak layak operasional.");
                }

                // Check driver work permit if mapped
                if (isset($driverMapping[$tId])) {
                    $driverParty = $driverMapping[$tId];
                    $permit = MiningWorkPermit::where('worker_party_id', $driverParty)
                        ->where('site_id', $siteId)
                        ->where('approval_status', 'APPROVED')
                        ->latest('valid_until')
                        ->first();

                    if (! $permit || $permit->isExpired()) {
                        throw new \DomainException("Operator {$driverParty} untuk truk {$tId} memiliki izin kerja (PTW) kedaluwarsa.");
                    }
                }
            }

            // 2. Validate shovel slots and area safety
            $slots = MiningShovelSlot::with('area')->whereIn('id', $shovelSlotIds)->get()->keyBy('id');
            foreach ($shovelSlotIds as $sId) {
                $slot = $slots->get($sId);
                if (! $slot) {
                    continue;
                }
                if ($slot->area && ! $slot->area->canOperate()) {
                    throw new \DomainException("Shovel slot {$slot->slot_code} berada pada area yang sedang dihentikan ({$slot->area->stop_reason}).");
                }
            }

            // 3. Build ordered matrix with deterministic tie-breaking (PR3)
            sort($truckIds);
            sort($shovelSlotIds);

            $n = count($truckIds);
            $m = count($shovelSlotIds);
            $numericMatrix = [];

            for ($i = 0; $i < $n; $i++) {
                $tId = $truckIds[$i];
                $row = [];
                for ($j = 0; $j < $m; $j++) {
                    $sId = $shovelSlotIds[$j];
                    $row[] = $costMatrix[$tId][$sId] ?? HungarianSolver::INF;
                }
                $numericMatrix[] = $row;
            }

            // 4. Solve via Hungarian algorithm
            $solverResult = $this->solver->solve($numericMatrix);

            // 5. Verify certificate dual potential (PR2)
            $certificate = [
                'u' => $solverResult->u,
                'v' => $solverResult->v,
            ];

            $isValid = $this->verifier->verify(
                costMatrix: $numericMatrix,
                assignment: $solverResult->assignment,
                totalCost: $solverResult->totalCost,
                certificate: $certificate
            );

            if (! $isValid) {
                throw new \RuntimeException('Sertifikat dual penugasan Hungarian tidak valid.');
            }

            // 6. Persist plan
            $plan = MiningDispatchPlan::create([
                'id' => (string) Str::uuid(),
                'plan_uuid' => $idempotencyKey,
                'site_id' => $siteId,
                'shift_code' => $shiftCode,
                'algorithm' => 'HUNGARIAN',
                'total_cost_seconds' => $solverResult->totalCost,
                'certificate_json' => json_encode($certificate),
                'status' => 'COMMITTED',
            ]);

            // 7. Persist assignments
            $assignments = [];
            foreach ($solverResult->assignment as $i => $j) {
                $tId = $truckIds[$i];
                $sId = $shovelSlotIds[$j];
                $cost = $numericMatrix[$i][$j];

                $assignment = MiningDispatchAssignment::create([
                    'id' => (string) Str::uuid(),
                    'dispatch_plan_id' => $plan->id,
                    'truck_id' => $tId,
                    'shovel_slot_id' => $sId,
                    'estimated_cycle_seconds' => $cost,
                    'is_override' => false,
                    'override_reason' => null,
                    'override_by_user_id' => null,
                ]);

                $assignments[] = $assignment;
            }

            return (object) [
                'plan' => $plan,
                'assignments' => $assignments,
            ];
        });
    }

    public function overrideAssignment(
        string $assignmentId,
        string $newShovelSlotId,
        int $userId,
        string $reason
    ): MiningDispatchAssignment {
        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('Alasan override penugasan wajib diisi.');
        }

        $assignment = MiningDispatchAssignment::findOrFail($assignmentId);
        $assignment->shovel_slot_id = $newShovelSlotId;
        $assignment->is_override = true;
        $assignment->override_reason = $reason;
        $assignment->override_by_user_id = $userId;
        $assignment->save();

        return $assignment;
    }
}
