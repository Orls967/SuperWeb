<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcher;
use Modules\Mining\Domain\Enums\EquipmentStatus;
use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\StateMachines\EquipmentStateMachine;
use Modules\Mining\Domain\StateMachines\HaulCycleStateMachine;

/**
 * Aksi Penugasan Armada Truk ke Shovel Berdasarkan Waktu Mulai Muat (D93-2, Algoritma M2).
 */
class DispatchHaulTruckAction
{
    private const LOADING_MINUTES = 4;

    private const TRAVEL_MINUTES = 10;

    public function __construct(
        private readonly NeedTimeTruckDispatcher $dispatcher,
        private readonly ClockInterface $clock,
        private readonly ?HaulCycleStateMachine $cycleStateMachine = null,
        private readonly ?EquipmentStateMachine $equipmentStateMachine = null
    ) {}

    /**
     * @param  array{
     *     site_id: string,
     *     pit_id: string,
     *     truck_id: string,
     *     shift_date: string,
     *     shift_code: string,
     *     target_payload_kg: int,
     *     dispatcher_id?: int,
     *     is_override?: bool,
     *     override_reason?: string,
     *     chosen_shovel_id?: string
     * }  $params
     */
    public function execute(array $params): MiningHaulCycle
    {
        return DB::transaction(function () use ($params) {
            $site = MiningSite::findOrFail($params['site_id']);
            $pit = MiningPit::findOrFail($params['pit_id']);
            $truck = MiningEquipment::findOrFail($params['truck_id']);

            $eqSm = $this->equipmentStateMachine ?? new EquipmentStateMachine();
            $truckStatus = $eqSm->resolveCurrentStatus($truck);

            if (in_array($truckStatus, [EquipmentStatus::BREAKDOWN, EquipmentStatus::UNDER_MAINTENANCE, EquipmentStatus::RETIRED], true)) {
                throw new DomainException("Unit truk {$truck->equipment_code} tidak dapat ditugaskan karena berstatus {$truckStatus->value}");
            }

            $shovels = MiningEquipment::where('site_id', $site->id)
                ->where('type', 'EXCAVATOR')
                ->whereIn('status', ['available', 'operating', 'standby'])
                ->get();

            if ($shovels->isEmpty()) {
                throw new DomainException('Tidak ada shovel/excavator yang tersedia untuk penugasan di site ini');
            }

            $selectedShovel = null;
            $isOverride = $params['is_override'] ?? false;
            $overrideReason = $params['override_reason'] ?? null;

            if ($isOverride) {
                if (empty($overrideReason)) {
                    throw new DomainException('Override dispatcher wajib menyertakan alasan tertulis resmi');
                }
                $selectedShovel = isset($params['chosen_shovel_id'])
                    ? MiningEquipment::findOrFail($params['chosen_shovel_id'])
                    : $shovels->first();
            } else {
                $shovelInput = [];
                foreach ($shovels as $s) {
                    $queueDepth = MiningHaulCycle::where('shovel_id', $s->id)
                        ->whereDate('shift_date', $params['shift_date'])
                        ->where('shift_code', $params['shift_code'])
                        ->whereNotIn('status', [HaulCycleStatus::COMPLETED->value, HaulCycleStatus::ABORTED->value])
                        ->count();
                    $shovelInput[] = [
                        'id' => (string) $s->id,
                        'loading_duration' => self::LOADING_MINUTES,
                        'travel_time_from_dump' => self::TRAVEL_MINUTES,
                        'available_at' => $queueDepth * self::LOADING_MINUTES,
                    ];
                }

                $truckInput = [
                    ['id' => (string) $truck->id, 'ready_at_dump' => 0],
                ];

                $availableTruckCount = MiningEquipment::where('site_id', $site->id)
                    ->where('type', 'HAUL_TRUCK')
                    ->whereIn('status', ['available', 'assigned', 'operating', 'standby'])
                    ->count();

                $dispatchResult = $this->dispatcher->dispatch(
                    shovels: $shovelInput,
                    trucks: $truckInput,
                    totalAvailableTrucks: max(1, $availableTruckCount)
                );

                $assignment = $dispatchResult->assignments[0] ?? null;
                $assignedShovelId = $assignment['shovel_id'] ?? $shovels->first()->id;
                $selectedShovel = MiningEquipment::findOrFail($assignedShovelId);
            }

            $lastSeq = MiningHaulCycle::where('truck_id', $truck->id)
                ->where('shift_date', $params['shift_date'])
                ->where('shift_code', $params['shift_code'])
                ->max('cycle_seq') ?? 0;

            $cycle = MiningHaulCycle::create([
                'site_id' => $site->id,
                'pit_id' => $pit->id,
                'truck_id' => $truck->id,
                'shovel_id' => $selectedShovel->id,
                'shift_date' => $params['shift_date'],
                'shift_code' => $params['shift_code'],
                'cycle_seq' => $lastSeq + 1,
                'status' => HaulCycleStatus::ASSIGNED->value,
                'target_payload_kg' => $params['target_payload_kg'],
                'actual_payload_kg' => 0,
                'distance_meters' => 5000,
                'fuel_consumed_ml' => 0,
                'ton_km_scaled' => 0,
                'is_override' => $isOverride,
                'override_reason' => $overrideReason,
                'override_by_user_id' => $params['dispatcher_id'] ?? null,
            ]);

            $cycleSm = $this->cycleStateMachine ?? new HaulCycleStateMachine($this->clock);
            $actorId = $params['dispatcher_id'] ? (string) $params['dispatcher_id'] : 'system';
            $notes = $isOverride ? "Dispatcher override: {$overrideReason}" : 'Penugasan otomatis NeedTimeTruckDispatcher M2';
            $cycleSm->recordInitialAssignment($cycle, $actorId, $notes);

            if ($truckStatus->canTransitionTo(EquipmentStatus::ASSIGNED)) {
                $eqSm->transition($truck, EquipmentStatus::ASSIGNED, $actorId, 'Ditugaskan ke siklus angkut '.$cycle->id);
            } else {
                $truck->update(['status' => EquipmentStatus::ASSIGNED->value]);
            }

            return $cycle;
        });
    }
}
