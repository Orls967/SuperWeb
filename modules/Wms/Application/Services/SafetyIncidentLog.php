<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Models\RobotSafetyIncident;

/**
 * Pencatatan log insiden keselamatan robotika simulasi append-only dan alur tinjauan berpersetujuan (X279-4).
 */
final readonly class SafetyIncidentLog
{
    public function __construct(
        private ApprovalEngineInterface $approvalEngine,
        private ClockInterface $clock,
    ) {}

    public function record(
        string $warehouseId,
        string $incidentCode,
        string $severity,
        string $incidentType,
        string $description,
        ?int $missionId = null,
        ?int $trafficZoneId = null,
        ?int $staffUserId = null,
    ): RobotSafetyIncident {
        /** @var RobotSafetyIncident|null $existing */
        $existing = RobotSafetyIncident::query()->where('incident_code', $incidentCode)->first();
        if ($existing !== null) {
            return $existing;
        }

        return RobotSafetyIncident::query()->create([
            'warehouse_id' => $warehouseId,
            'incident_code' => $incidentCode,
            'mission_id' => $missionId,
            'traffic_zone_id' => $trafficZoneId,
            'staff_user_id' => $staffUserId,
            'severity' => $severity,
            'incident_type' => $incidentType,
            'description' => $description,
            'status' => 'reported',
            'simulation_label' => 'asumsi_simulasi',
            'recorded_at' => $this->clock->now(),
        ]);
    }

    public function review(RobotSafetyIncident $incident, int $reviewerUserId, string $decisionStatus = 'reviewed'): RobotSafetyIncident
    {
        if (! in_array($decisionStatus, ['reviewed', 'closed'], true)) {
            throw new InvalidArgumentException('Status tinjauan insiden keselamatan tidak valid.');
        }

        return DB::transaction(function () use ($incident, $reviewerUserId, $decisionStatus): RobotSafetyIncident {
            /** @var RobotSafetyIncident $locked */
            $locked = RobotSafetyIncident::query()->lockForUpdate()->findOrFail($incident->id);

            $this->approvalEngine->submit(
                'wms_robot_safety_incident',
                (string) $locked->id,
                $reviewerUserId,
                ['incident_code' => $locked->incident_code, 'severity' => $locked->severity],
            );

            $locked->update([
                'status' => $decisionStatus,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => $this->clock->now(),
            ]);

            return $locked->fresh();
        });
    }
}
