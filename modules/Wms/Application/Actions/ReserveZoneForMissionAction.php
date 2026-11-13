<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Services\RobotMissionTransitionService;
use Modules\Wms\Domain\Algorithms\ZoneReservationScheduler;
use Modules\Wms\Domain\Exceptions\RobotMissionTransitionException;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Action reservasi zona lalu lintas J1 dan penegakan interlock staf (D279-4, D96).
 */
final readonly class ReserveZoneForMissionAction
{
    public function __construct(
        private ZoneReservationScheduler $scheduler,
        private RobotMissionTransitionService $transitionService,
        private ClockInterface $clock,
    ) {}

    /**
     * Pesan interval [start, end) untuk misi pada zona target lalu gerakkan misi ke status moving.
     *
     * @return array{reservation: ZoneReservation, mission: RobotMission, start_min: int, end_min: int}
     */
    public function reserveAndStartMoving(
        RobotMission $mission,
        TrafficZone $zone,
        int $earliestStartMin,
        int $durationMin,
        ?int $actorUserId = null,
    ): array {
        return DB::transaction(function () use ($mission, $zone, $earliestStartMin, $durationMin, $actorUserId): array {
            /** @var TrafficZone $lockedZone */
            $lockedZone = TrafficZone::query()->lockForUpdate()->findOrFail($zone->id);
            /** @var RobotMission $lockedMission */
            $lockedMission = RobotMission::query()->lockForUpdate()->findOrFail($mission->id);

            if ((bool) $lockedZone->staff_blocked) {
                throw RobotMissionTransitionException::guardFailed(
                    'ZONE_INTERLOCK_STAFF_BLOCKED',
                    (string) $lockedMission->status,
                    RobotMissionStateMachine::STATUS_MOVING,
                    "Zona {$lockedZone->zone_code} diblokir staf: ".($lockedZone->block_reason ?? 'interlock'),
                );
            }

            $activeReservations = ZoneReservation::query()
                ->where('traffic_zone_id', $lockedZone->id)
                ->where('status', 'active')
                ->orderBy('start_min')
                ->lockForUpdate()
                ->get()
                ->map(static fn (ZoneReservation $r): array => [
                    'start' => (int) $r->start_min,
                    'end' => (int) $r->end_min,
                ])
                ->all();

            $staffBlocks = [];
            if ($lockedZone->block_start_min !== null && $lockedZone->block_end_min !== null) {
                $staffBlocks[] = [
                    'start' => (int) $lockedZone->block_start_min,
                    'end' => (int) $lockedZone->block_end_min,
                ];
            }

            $scheduled = $this->scheduler->schedule(
                capacity: (int) $lockedZone->capacity_k,
                reservations: $activeReservations,
                staffBlocks: $staffBlocks,
                duration: $durationMin,
                earliestStart: $earliestStartMin,
            );

            $reservation = ZoneReservation::query()->create([
                'warehouse_id' => $lockedZone->warehouse_id,
                'traffic_zone_id' => $lockedZone->id,
                'mission_id' => $lockedMission->id,
                'start_min' => $scheduled['start'],
                'end_min' => $scheduled['end'],
                'reservation_kind' => 'mission',
                'status' => 'active',
            ]);

            $lockedMission->traffic_zone_id = $lockedZone->id;
            $lockedMission->scheduled_start_min = $scheduled['start'];
            $lockedMission->duration_min = $durationMin;
            $lockedMission->save();

            $updatedMission = $this->transitionService->transition(
                $lockedMission,
                RobotMissionStateMachine::STATUS_MOVING,
                $actorUserId,
                'ZONE_RESERVED_J1',
                "Reservasi zona {$lockedZone->zone_code} [{$scheduled['start']}, {$scheduled['end']})",
                [
                    'has_active_zone_reservation' => true,
                    'zone_staff_blocked' => false,
                    'interlock_passed' => true,
                ],
            );

            return [
                'reservation' => $reservation,
                'mission' => $updatedMission,
                'start_min' => $scheduled['start'],
                'end_min' => $scheduled['end'],
            ];
        });
    }

    /**
     * Lepaskan reservasi aktif yang sudah melewati batas waktu (robotics:reservation-sweep).
     */
    public function sweepExpired(int $currentMin): int
    {
        return ZoneReservation::query()
            ->where('status', 'active')
            ->where('end_min', '<=', $currentMin)
            ->update([
                'status' => 'expired',
                'released_at' => $this->clock->now(),
                'release_reason' => "expired_at_min_{$currentMin}",
            ]);
    }

    /**
     * Aktifkan atau lepas blokir staf pada zona lalu lintas (interlock).
     */
    public function setStaffBlock(TrafficZone $zone, bool $blocked, ?int $startMin = null, ?int $endMin = null, ?string $reason = null): TrafficZone
    {
        return DB::transaction(function () use ($zone, $blocked, $startMin, $endMin, $reason): TrafficZone {
            /** @var TrafficZone $locked */
            $locked = TrafficZone::query()->lockForUpdate()->findOrFail($zone->id);
            $locked->update([
                'staff_blocked' => $blocked,
                'block_start_min' => $blocked ? $startMin : null,
                'block_end_min' => $blocked ? $endMin : null,
                'block_reason' => $blocked ? ($reason ?? 'staff_presence_interlock') : null,
            ]);

            return $locked->fresh();
        });
    }
}
