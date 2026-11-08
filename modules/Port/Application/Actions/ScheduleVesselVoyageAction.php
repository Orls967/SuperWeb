<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Action Penjadwalan & Inisialisasi Pelayaran Kapal Niaga.
 */
final class ScheduleVesselVoyageAction
{
    public function __construct(
        private readonly PortFleetService $fleetService,
        private readonly VoyageExecutionService $voyageService
    ) {}

    public function execute(
        int $vesselId,
        string $voyageCode,
        string $voyageNumber,
        string $originPort,
        string $destinationPort,
        string $loadPort,
        string $dischargePort,
        CarbonImmutable $plannedStart,
        CarbonImmutable $plannedEnd,
        float $plannedDistanceNm,
        float $targetSpeedKnots = 13.5,
        ?int $charterPartyId = null
    ): FleetVoyage {
        $vessel = FleetVessel::findOrFail($vesselId);

        if ($plannedEnd->isBefore($plannedStart)) {
            throw new DomainException('Waktu kedatangan yang direncanakan tidak boleh lebih awal dari waktu keberangkatan.');
        }

        if ($plannedDistanceNm <= 0.0) {
            throw new DomainException('Jarak pelayaran harus lebih besar dari 0 mil laut.');
        }

        // Audit kelaiklautan sebelum jadwal diterbitkan
        $audit = $this->fleetService->auditSeaworthiness($vesselId, $plannedStart);
        if (! $audit['is_seaworthy']) {
            throw new DomainException('Kapal tidak layak melaut: '.implode('; ', $audit['reasons']));
        }

        // Hitung estimasi waktu pelayaran (ETA)
        $estimatedHours = $plannedDistanceNm / max(5.0, $targetSpeedKnots);
        $minimumArrivalTime = $plannedStart->addHours((int) ceil($estimatedHours));

        if ($plannedEnd->isBefore($minimumArrivalTime)) {
            throw new DomainException("Jadwal tidak realistis: Jarak {$plannedDistanceNm} NM dengan kecepatan {$targetSpeedKnots} knots memerlukan minimal {$estimatedHours} jam.");
        }

        return $this->voyageService->planVoyage([
            'voyage_code' => $voyageCode,
            'vessel_id' => $vessel->id,
            'charter_party_id' => $charterPartyId,
            'voyage_number' => $voyageNumber,
            'origin_port' => $originPort,
            'destination_port' => $destinationPort,
            'load_port' => $loadPort,
            'discharge_port' => $dischargePort,
            'planned_start_at' => $plannedStart,
            'planned_end_at' => $plannedEnd,
            'planned_distance_nm' => $plannedDistanceNm,
            'planned_speed_knots' => $targetSpeedKnots,
            'operational_notes' => [
                'estimated_steaming_hours' => round($estimatedHours, 1),
                'seaworthiness_verified_at' => CarbonImmutable::now()->toIso8601String(),
            ],
        ]);
    }
}
