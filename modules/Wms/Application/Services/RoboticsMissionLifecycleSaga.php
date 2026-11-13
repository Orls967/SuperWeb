<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\AllocateRobotMissionsAction;
use Modules\Wms\Application\Actions\CompleteRobotMissionAction;
use Modules\Wms\Application\Actions\ReserveZoneForMissionAction;
use Modules\Wms\Application\Actions\RetryOrFallbackRobotMissionAction;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\TrafficZone;
use Throwable;

/**
 * SAGA berkompensasi eksekusi misi robotika (T-4):
 * Langkah: alokasi -> reservasi zona J1 -> eksekusi perangkat & penyelesaian -> konfirmasi WarehouseTask.
 * Kompensasi saat gagal: pelepasan reservasi zona aktif + pembuatan task manual Fase 41 + re-plan wave.
 */
final readonly class RoboticsMissionLifecycleSaga
{
    public function __construct(
        private AllocateRobotMissionsAction $allocateAction,
        private ReserveZoneForMissionAction $reserveAction,
        private CompleteRobotMissionAction $completeAction,
        private RetryOrFallbackRobotMissionAction $fallbackAction,
    ) {}

    /**
     * @param  list<array{id: string|int, size: int, carrier: string, cutoff: int}>  $waveOrders
     * @return array{outcome: string, mission: RobotMission, compensated: bool}
     */
    public function execute(
        RobotMission $mission,
        TrafficZone $zone,
        int $earliestStartMin,
        int $durationMin,
        int $operatorUserId,
        int $waveCapacity = 70,
        array $waveOrders = [],
    ): array {
        try {
            if ($mission->status === 'queued') {
                $this->allocateAction->execute((string) $mission->warehouse_id, "SAGA-{$mission->mission_code}", $operatorUserId);
                $mission->refresh();
            }

            if ($mission->status === 'assigned') {
                $res = $this->reserveAction->reserveAndStartMoving($mission, $zone, $earliestStartMin, $durationMin, $operatorUserId);
                $mission = $res['mission'];
            }

            $completed = $this->completeAction->execute($mission, $operatorUserId);

            return [
                'outcome' => 'COMPLETED',
                'mission' => $completed,
                'compensated' => false,
            ];
        } catch (Throwable $e) {
            $fallback = $this->fallbackAction->fallbackAndReplan(
                $mission->fresh(),
                $operatorUserId,
                'SAGA_COMPENSATION:'.substr($e->getMessage(), 0, 80),
                $waveCapacity,
                $waveOrders,
            );

            return [
                'outcome' => 'COMPENSATED_FALLBACK_MANUAL',
                'mission' => $fallback['mission'],
                'compensated' => true,
            ];
        }
    }
}
