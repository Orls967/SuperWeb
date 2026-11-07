<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\Experience\WaitPerServiceGovernor;
use Modules\Venue\Domain\DTOs\BarQueueMetricsDTO;
use Modules\Venue\Domain\DTOs\WpsStationAnalysisDTO;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\ZoneWpsMetric;
use Modules\Venue\Domain\ValueObjects\ServiceStationThroughput;

/**
 * Service to aggregate bar counter queue depths and calculate WPS scores.
 * Recommends staffing reallocations when wait times exceed threshold (117.4).
 */
class BarQueueTelemetryAggregatorService
{
    public const MAX_ACCEPTABLE_WAIT_SECONDS = 180; // 3 minutes maximum acceptable bar wait

    public function __construct(
        protected WaitPerServiceGovernor $governorEngine
    ) {}

    /**
     * Record and evaluate bar queue telemetry for a specific service station.
     */
    public function recordStationMetrics(
        EntertainmentZone $zone,
        EntertainmentEvent $event,
        int $stationNumber,
        int $currentQueueCount,
        int $activeBartenders,
        float $averageServiceSeconds = 45.0
    ): WpsStationAnalysisDTO {
        $activeBartenders = max(1, $activeBartenders);
        $arrivalRatePerMinute = ($currentQueueCount * 1.5);
        $serviceRatePerServerPerMinute = 60.0 / max(10.0, $averageServiceSeconds);

        $throughput = new ServiceStationThroughput(
            stationId: sprintf('BAR-STATION-%d', $stationNumber),
            activeServers: $activeBartenders,
            arrivalRatePerMinute: $arrivalRatePerMinute,
            serviceRatePerServerPerMinute: $serviceRatePerServerPerMinute
        );

        $evaluation = $this->governorEngine->evaluateWaitPerService($throughput);

        $wpsScore = (float) $evaluation['wps_score_100'];
        $estimatedWaitSec = (int) ($evaluation['estimated_wait_seconds'] ?? 0);
        $status = (string) $evaluation['service_level_status'];

        $reinforcementRecommended = $estimatedWaitSec > self::MAX_ACCEPTABLE_WAIT_SECONDS || $status === 'SEVERELY_DEGRADED';

        // Persist metric record
        ZoneWpsMetric::create([
            'zone_id' => $zone->id,
            'event_id' => $event->id,
            'station_number' => $stationNumber,
            'active_servers_count' => $activeBartenders,
            'arrival_rate_bps' => (int) ($arrivalRatePerMinute * 100),
            'service_rate_bps' => (int) ($serviceRatePerServerPerMinute * 100),
            'utilization_bps' => min(9900, (int) (($arrivalRatePerMinute / max(1.0, (float) ($activeBartenders * $serviceRatePerServerPerMinute))) * 10000)),
            'average_wait_minutes' => (int) ceil($estimatedWaitSec / 60),
            'wps_score' => (int) $wpsScore,
            'reinforcement_needed' => $reinforcementRecommended,
            'recommended_additional_servers' => $reinforcementRecommended ? max(1, (int) ceil(($estimatedWaitSec - 120) / 60)) : 0,
            'recorded_at' => now(),
        ]);

        return new WpsStationAnalysisDTO(
            stationId: sprintf('BAR-STATION-%d', $stationNumber),
            wpsScore: $wpsScore,
            estimatedWaitSeconds: $estimatedWaitSec,
            serviceLevelStatus: $status,
            reinforcementRecommended: $reinforcementRecommended,
            recommendedStaffAddition: $reinforcementRecommended ? max(1, (int) ceil(($estimatedWaitSec - 120) / 60)) : 0
        );
    }

    /**
     * Aggregate overall venue bar service health for an ongoing event.
     */
    public function aggregateVenueServiceHealth(EntertainmentEvent $event): BarQueueMetricsDTO
    {
        $metrics = ZoneWpsMetric::where('event_id', $event->id)
            ->where('created_at', '>=', now()->subMinutes(60))
            ->get();

        if ($metrics->isEmpty()) {
            return new BarQueueMetricsDTO(
                meanWpsScore: 100.0,
                longestWaitSeconds: 0,
                saturatedStationsCount: 0,
                totalActiveStaff: 10,
                healthRating: 'EXCELLENT'
            );
        }

        $meanScore = round((float) $metrics->avg('wps_score'), 1);
        $maxWait = (int) ($metrics->max('average_wait_minutes') * 60);
        $saturatedCount = $metrics->where('reinforcement_needed', true)->count();
        $totalStaff = (int) $metrics->sum('active_servers_count');

        $rating = 'EXCELLENT';
        if ($meanScore < 50.0 || $maxWait > 360) {
            $rating = 'POOR';
        } elseif ($meanScore < 75.0 || $maxWait > 200) {
            $rating = 'SATISFACTORY';
        }

        return new BarQueueMetricsDTO(
            meanWpsScore: $meanScore,
            longestWaitSeconds: $maxWait,
            saturatedStationsCount: $saturatedCount,
            totalActiveStaff: $totalStaff,
            healthRating: $rating
        );
    }
}
