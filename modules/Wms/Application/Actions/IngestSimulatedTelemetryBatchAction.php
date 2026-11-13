<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Services\RobotTelemetryIngestor;
use Modules\Wms\Application\Services\SafetyIncidentLog;
use Modules\Wms\Contracts\SimulatedDeviceAdapterInterface;
use Modules\Wms\Domain\Algorithms\RobotEnergyConsumptionEstimator;
use Modules\Wms\Domain\Algorithms\RobotHealthScorer;
use Modules\Wms\Models\Robot;

/**
 * Mengumpulkan batch telemetri perangkat robotika dari adapter simulasi/perangkat,
 * memperbarui skor kesehatan armada, serta mencatat insiden keselamatan jika ambang terlampaui.
 */
final readonly class IngestSimulatedTelemetryBatchAction
{
    public const int CRITICAL_HEALTH_THRESHOLD = 400;

    public const int LOW_BATTERY_ALERT_PCT = 15;

    public function __construct(
        private SimulatedDeviceAdapterInterface $deviceAdapter,
        private RobotTelemetryIngestor $telemetryIngestor,
        private RobotHealthScorer $healthScorer,
        private RobotEnergyConsumptionEstimator $energyEstimator,
        private SafetyIncidentLog $safetyIncidentLog,
    ) {}

    /**
     * @return array{
     *     warehouse_id:string,
     *     tick_key:string,
     *     robots_processed:int,
     *     healthy_count:int,
     *     degraded_count:int,
     *     incidents_opened:int,
     *     avg_battery_pct:int,
     *     avg_health_score:int,
     *     details:list<array{robot_code:string, battery_pct:int, health_score:int, max_range_m:int, incident_opened:bool}>
     * }
     */
    public function execute(string $warehouseId, string $tickKey, int $seed = 279): array
    {
        return DB::transaction(function () use ($warehouseId, $tickKey, $seed): array {
            $robots = Robot::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id')
                ->get();

            if ($robots->isEmpty()) {
                return [
                    'warehouse_id' => $warehouseId,
                    'tick_key' => $tickKey,
                    'robots_processed' => 0,
                    'healthy_count' => 0,
                    'degraded_count' => 0,
                    'incidents_opened' => 0,
                    'avg_battery_pct' => 0,
                    'avg_health_score' => 0,
                    'details' => [],
                ];
            }

            $healthy = 0;
            $degraded = 0;
            $incidentsOpened = 0;
            $sumBattery = 0;
            $sumHealth = 0;
            $details = [];

            foreach ($robots as $idx => $robot) {
                $poll = $this->deviceAdapter->pollTelemetry((string) $robot->robot_code, $seed + $idx);
                $battery = (int) $poll['battery_pct'];
                $errorCount = (int) $poll['error_count'];
                $downtime = $robot->is_online ? 0 : 15;

                $this->telemetryIngestor->ingest($robot, $battery, $errorCount, $downtime, $tickKey);
                $scored = $this->healthScorer->score($battery, $errorCount, $downtime);
                $scoreVal = (int) $scored['health_score'];
                $maxRangeM = $this->energyEstimator->maxSafeRangeMeters($battery);

                $incidentOpened = false;
                if ($scoreVal < self::CRITICAL_HEALTH_THRESHOLD || $battery < self::LOW_BATTERY_ALERT_PCT) {
                    $incidentCode = "INC-TEL-{$robot->id}-{$tickKey}";
                    $this->safetyIncidentLog->record(
                        warehouseId: $warehouseId,
                        incidentCode: $incidentCode,
                        severity: $battery < self::LOW_BATTERY_ALERT_PCT ? 'high' : 'medium',
                        incidentType: 'telemetry_threshold_breach',
                        description: "Robot {$robot->robot_code} skor kesehatan {$scoreVal}, baterai {$battery}%, error {$errorCount}.",
                    );
                    $incidentsOpened++;
                    $incidentOpened = true;
                }

                if ($scoreVal >= 700) {
                    $healthy++;
                } else {
                    $degraded++;
                }

                $sumBattery += $battery;
                $sumHealth += $scoreVal;
                $details[] = [
                    'robot_code' => (string) $robot->robot_code,
                    'battery_pct' => $battery,
                    'health_score' => $scoreVal,
                    'max_range_m' => $maxRangeM,
                    'incident_opened' => $incidentOpened,
                ];
            }

            $count = $robots->count();

            return [
                'warehouse_id' => $warehouseId,
                'tick_key' => $tickKey,
                'robots_processed' => $count,
                'healthy_count' => $healthy,
                'degraded_count' => $degraded,
                'incidents_opened' => $incidentsOpened,
                'avg_battery_pct' => intdiv($sumBattery, $count),
                'avg_health_score' => intdiv($sumHealth, $count),
                'details' => $details,
            ];
        });
    }
}
