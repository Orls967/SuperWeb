<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Venue\Domain\Algorithms\Experience\WaitPerServiceGovernor;
use Modules\Venue\Domain\DTOs\WpsStationAnalysisDTO;
use Modules\Venue\Domain\Models\ZoneWpsMetric;
use Modules\Venue\Domain\ValueObjects\ServiceStationThroughput;

/**
 * Layanan Optimalisasi Throughput Antrean Bar & Kepuasan Pengunjung (WPS Optimizer).
 * Mengintegrasikan teori antrean multi-server, pengukuran waktu tunggu rata-rata,
 * dan penerbitan rekomendasi shift kasir cadangan ke modul HCM.
 */
final class BarQueueThroughputOptimizerService
{
    public function __construct(
        private readonly WaitPerServiceGovernor $wpsGovernor
    ) {}

    /**
     * Analisis stasiun bar dan rekam riwayat metrik kepuasan layanan WPS.
     */
    public function analyzeAndRecordStationWps(
        int $zoneId,
        int $stationNumber,
        int $activeBartenders,
        int $arrivalRateOrdersPerMinBps,
        int $serviceRatePerBartenderBps,
        ?int $eventId = null
    ): WpsStationAnalysisDTO {
        $result = $this->wpsGovernor->calculateWps(
            stationId: $stationNumber,
            activeServersCount: $activeBartenders,
            arrivalRatePerMinuteBps: $arrivalRateOrdersPerMinBps,
            serviceRatePerMinuteBps: $serviceRatePerBartenderBps,
            maxTolerableWaitMinutes: 15
        );

        ZoneWpsMetric::create([
            'event_id' => $eventId,
            'zone_id' => $zoneId,
            'station_number' => $stationNumber,
            'active_servers_count' => $activeBartenders,
            'arrival_rate_bps' => $arrivalRateOrdersPerMinBps,
            'service_rate_bps' => $serviceRatePerBartenderBps,
            'utilization_bps' => $result->utilizationRateBps,
            'average_wait_minutes' => $result->averageWaitTimeMinutes,
            'wps_score' => $result->wpsScore,
            'reinforcement_needed' => $result->reinforcementNeeded,
            'recommended_additional_servers' => $result->recommendedAdditionalServers,
            'recorded_at' => Carbon::now(),
        ]);

        return new WpsStationAnalysisDTO(
            stationNumber: $stationNumber,
            zoneId: $zoneId,
            activeServers: $activeBartenders,
            utilizationBps: $result->utilizationRateBps,
            averageWaitMinutes: $result->averageWaitTimeMinutes,
            wpsScore: $result->wpsScore,
            reinforcementNeeded: $result->reinforcementNeeded,
            recommendedServersToAdd: $result->recommendedAdditionalServers
        );
    }

    /**
     * Dapatkan ringkasan throughput seluruh stasiun bar pada suatu zona.
     *
     * @return Collection<int, ServiceStationThroughput>
     */
    public function getZoneStationsThroughputSummary(int $zoneId): Collection
    {
        $metrics = ZoneWpsMetric::where('zone_id', $zoneId)
            ->latest('id')
            ->get()
            ->unique('station_number');

        return $metrics->map(fn (ZoneWpsMetric $m) => new ServiceStationThroughput(
            stationNumber: $m->station_number,
            activeBartendersCount: $m->active_servers_count,
            ordersCompletedLastHour: (int) ($m->service_rate_bps * $m->active_servers_count / 100),
            grossSalesLastHourIdr: 45_000_000,
            averagePreparationSeconds: max(30, $m->average_wait_minutes * 60),
            wpsScore: $m->wps_score
        ));
    }

    /**
     * Hitung indeks kepuasan tamu agregat (Overall Venue WPS Index) untuk malam ini.
     */
    public function computeOverallVenueWpsIndex(int $venueId): int
    {
        $metrics = ZoneWpsMetric::query()->latest('id')->limit(50)->get();

        if ($metrics->isEmpty()) {
            return 85; // Bawaan normal
        }

        return (int) intdiv((int) $metrics->sum('wps_score'), $metrics->count());
    }
}
