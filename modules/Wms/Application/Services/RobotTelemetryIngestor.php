<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Algorithms\RobotHealthScorer;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotTelemetry;

/**
 * Ingestor telemetri armada robot simulasi yang idempoten per (robot_id, cap waktu)
 * serta menerbitkan alert anomali ke TaskInboxService (X279-2).
 */
final readonly class RobotTelemetryIngestor
{
    public function __construct(
        private RobotHealthScorer $scorer,
        private TaskInboxService $taskInbox,
        private ClockInterface $clock,
    ) {}

    public function ingest(
        Robot $robot,
        int $batteryPct,
        int $errorCount,
        int $downtimeMinutes,
        string $timestampKey,
    ): RobotTelemetry {
        $idempotencyKey = "tel:{$robot->id}:{$timestampKey}";

        /** @var RobotTelemetry|null $existing */
        $existing = RobotTelemetry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($robot, $batteryPct, $errorCount, $downtimeMinutes, $idempotencyKey): RobotTelemetry {
            /** @var Robot $lockedRobot */
            $lockedRobot = Robot::query()->lockForUpdate()->findOrFail($robot->id);
            $eval = $this->scorer->score($batteryPct, $errorCount, $downtimeMinutes);

            $telemetry = RobotTelemetry::query()->create([
                'warehouse_id' => $lockedRobot->warehouse_id,
                'robot_id' => $lockedRobot->id,
                'battery_pct' => max(0, min(100, $batteryPct)),
                'error_count' => max(0, $errorCount),
                'downtime_minutes' => max(0, $downtimeMinutes),
                'health_score' => $eval['health_score'],
                'anomaly_code' => $eval['anomaly_code'],
                'idempotency_key' => $idempotencyKey,
                'recorded_at' => $this->clock->now(),
            ]);

            $lockedRobot->update([
                'battery_pct' => max(0, min(100, $batteryPct)),
                'health_score' => $eval['health_score'],
            ]);

            if ($eval['is_anomaly']) {
                $this->taskInbox->createTask([
                    'title' => "Anomali Telemetri Robot {$lockedRobot->robot_code}",
                    'description' => "Kode anomali {$eval['anomaly_code']} (skor {$eval['health_score']}/1000, baterai {$batteryPct}%).",
                    'module' => 'Wms',
                    'entity_type' => 'robot',
                    'entity_id' => (string) $lockedRobot->id,
                    'priority' => 'high',
                ]);
            }

            return $telemetry;
        });
    }
}
