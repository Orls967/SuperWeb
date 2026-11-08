<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Domain\Models\FleetNoonReport;
use Modules\Port\Domain\Models\FleetSeaPassage;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Port\Domain\Models\FleetVoyageLeg;
use Modules\Port\Domain\Models\FleetWeatherRouting;
use Modules\Port\Domain\StateMachines\VoyageStateMachine;
use Modules\Shared\Domain\Algorithms\Port\BunkerFuelConsumptionOptimizer;

/**
 * Service Eksekusi & Manajemen Pelayaran Kapal (Voyage Execution Service).
 * Mengatur siklus pelayaran, pelaporan tengah hari (Noon Reports), rencana rute aman (ECDIS & UKC),
 * serta mitigasi rute cuaca buruk (Weather Routing).
 */
final class VoyageExecutionService
{
    public function __construct(
        private readonly VoyageStateMachine $stateMachine = new VoyageStateMachine,
        private readonly BunkerFuelConsumptionOptimizer $bunkerOptimizer = new BunkerFuelConsumptionOptimizer
    ) {}

    /**
     * Buat rencana pelayaran baru (Voyage Planning).
     *
     * @param array{
     *     voyage_code: string,
     *     vessel_id: int,
     *     charter_party_id?: int|null,
     *     voyage_number: string,
     *     voyage_type?: string,
     *     origin_port: string,
     *     destination_port: string,
     *     load_port: string,
     *     discharge_port: string,
     *     planned_start_at: CarbonImmutable,
     *     planned_end_at: CarbonImmutable,
     *     planned_distance_nm: float,
     *     planned_speed_knots?: float,
     *     operational_notes?: array
     * } $data
     */
    public function planVoyage(array $data): FleetVoyage
    {
        $vessel = FleetVessel::findOrFail($data['vessel_id']);

        return FleetVoyage::create([
            'voyage_code' => $data['voyage_code'],
            'vessel_id' => $vessel->id,
            'charter_party_id' => $data['charter_party_id'] ?? null,
            'voyage_number' => $data['voyage_number'],
            'voyage_type' => $data['voyage_type'] ?? 'TRAMP',
            'origin_port' => $data['origin_port'],
            'destination_port' => $data['destination_port'],
            'load_port' => $data['load_port'],
            'discharge_port' => $data['discharge_port'],
            'planned_start_at' => $data['planned_start_at']->toDateTimeString(),
            'planned_end_at' => $data['planned_end_at']->toDateTimeString(),
            'planned_distance_nm' => $data['planned_distance_nm'],
            'planned_speed_knots' => $data['planned_speed_knots'] ?? 13.0,
            'status' => VoyageStateMachine::STATUS_PLANNED,
            'operational_notes' => $data['operational_notes'] ?? [],
        ]);
    }

    /**
     * Transisikan status siklus pelayaran dengan validasi state machine.
     */
    public function transitionVoyageStatus(int $voyageId, string $targetStatus, array $context = []): FleetVoyage
    {
        $voyage = FleetVoyage::findOrFail($voyageId);
        $newStatus = $this->stateMachine->transition($voyage->status, $targetStatus, $context);

        $updates = ['status' => $newStatus];

        if ($newStatus === VoyageStateMachine::STATUS_BALLAST_PASSAGE && ! $voyage->actual_start_at) {
            $updates['actual_start_at'] = CarbonImmutable::now()->toDateTimeString();
        }

        if ($newStatus === VoyageStateMachine::STATUS_COMPLETED && ! $voyage->actual_end_at) {
            $updates['actual_end_at'] = CarbonImmutable::now()->toDateTimeString();
        }

        $voyage->update($updates);

        return $voyage->fresh();
    }

    /**
     * Tambahkan leg pelayaran (misal ballast leg, laden leg, dsb).
     */
    public function addVoyageLeg(
        int $voyageId,
        int $sequence,
        string $legType,
        string $departureWaypoint,
        string $arrivalWaypoint,
        float $distanceNm,
        float $targetSpeedKnots
    ): FleetVoyageLeg {
        return FleetVoyageLeg::create([
            'voyage_id' => $voyageId,
            'leg_sequence' => $sequence,
            'leg_type' => $legType,
            'departure_waypoint' => $departureWaypoint,
            'arrival_waypoint' => $arrivalWaypoint,
            'distance_nm' => $distanceNm,
            'target_speed_knots' => $targetSpeedKnots,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Catat laporan tengah hari harian (Noon Report) dan perbarui sisa bunker ROB.
     */
    public function recordNoonReport(
        int $voyageId,
        CarbonImmutable $reportDate,
        float $latitude,
        float $longitude,
        float $courseDegrees,
        float $speedGpsKnots,
        float $distance24hNm,
        float $windBeaufort,
        string $windDirection,
        float $seaStateDouglas,
        float $engineRpm,
        float $engineSlipPct,
        float $vlsfoConsumed24h,
        float $lsmgoConsumed24h,
        ?string $remarks = null
    ): FleetNoonReport {
        $voyage = FleetVoyage::with('vessel.bunkerTanks')->findOrFail($voyageId);
        $vessel = $voyage->vessel;

        // Ambil ROB terakhir atau hitung dari tangki
        $lastReport = FleetNoonReport::where('voyage_id', $voyageId)
            ->orderByDesc('report_date')
            ->first();

        $vlsfoRob = $lastReport ? $lastReport->vlsfo_rob_mt : (float) $vessel->bunkerTanks()->where('fuel_grade', 'VLSFO')->sum('current_rob_metric_tons');
        $lsmgoRob = $lastReport ? $lastReport->lsmgo_rob_mt : (float) $vessel->bunkerTanks()->where('fuel_grade', 'LSMGO')->sum('current_rob_metric_tons');

        $newVlsfoRob = max(0.0, $vlsfoRob - $vlsfoConsumed24h);
        $newLsmgoRob = max(0.0, $lsmgoRob - $lsmgoConsumed24h);

        // Update tangki bunker kapal
        $vlsfoTank = $vessel->bunkerTanks()->where('fuel_grade', 'VLSFO')->first();
        if ($vlsfoTank) {
            $vlsfoTank->update(['current_rob_metric_tons' => $newVlsfoRob]);
        }

        $lsmgoTank = $vessel->bunkerTanks()->where('fuel_grade', 'LSMGO')->first();
        if ($lsmgoTank) {
            $lsmgoTank->update(['current_rob_metric_tons' => $newLsmgoRob]);
        }

        // Akumulasi jarak pelayaran aktual
        $voyage->increment('actual_distance_nm', $distance24hNm);

        return FleetNoonReport::create([
            'voyage_id' => $voyageId,
            'vessel_id' => $vessel->id,
            'report_date' => $reportDate->toDateString(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'course_degrees' => $courseDegrees,
            'speed_log_knots' => $speedGpsKnots,
            'speed_gps_knots' => $speedGpsKnots,
            'distance_last_24h_nm' => $distance24hNm,
            'wind_force_beaufort' => $windBeaufort,
            'wind_direction' => $windDirection,
            'sea_state_douglas' => $seaStateDouglas,
            'engine_rpm' => $engineRpm,
            'engine_slip_percentage' => $engineSlipPct,
            'vlsfo_consumed_24h_mt' => $vlsfoConsumed24h,
            'lsmgo_consumed_24h_mt' => $lsmgoConsumed24h,
            'vlsfo_rob_mt' => round($newVlsfoRob, 2),
            'lsmgo_rob_mt' => round($newLsmgoRob, 2),
            'remarks' => $remarks,
        ]);
    }

    /**
     * Buat rencana lintasan laut (Sea Passage Plan) dan validasi batas keselamatan ECDIS & UKC.
     */
    public function createPassagePlan(
        int $voyageId,
        string $planRef,
        array $waypoints,
        float $minUkcM = 2.50,
        bool $isMarpolSpecialArea = false,
        bool $isSecaArea = false
    ): FleetSeaPassage {
        // Validasi UKC terhadap standar minimum keselamatan maritim
        if ($minUkcM < 1.50) {
            throw new DomainException('Under Keel Clearance (UKC) di bawah batas minimum keselamatan IMO (1.50 meter).');
        }

        return FleetSeaPassage::create([
            'voyage_id' => $voyageId,
            'passage_plan_ref' => $planRef,
            'waypoints' => $waypoints,
            'under_keel_clearance_min_m' => $minUkcM,
            'ecdis_cross_track_limit_checked' => true,
            'marpol_special_area_transit' => $isMarpolSpecialArea,
            'seca_transit' => $isSecaArea,
        ]);
    }

    /**
     * Catat peringatan pola cuaca buruk dan rekomendasi deviasi rute (Weather Routing Advisory).
     * Misal: Badai Sumatra Squall di Selat Malaka.
     */
    public function evaluateWeatherAdvisory(
        int $voyageId,
        string $weatherAlert,
        float $waveHeightM,
        float $speedReductionKnots,
        float $diversionDistanceNm
    ): FleetWeatherRouting {
        $voyage = FleetVoyage::findOrFail($voyageId);

        // Estimasi dampak konsumsi bahan bakar: jika deviasi diambil, kurangi beban mesin untuk menghindari gelombang tinggi
        $fuelSavings = 0.0;
        if ($waveHeightM >= 3.5) {
            // Hindari kerusakan lambung dan peningkatan konsumsi akibat slip propeller tinggi
            $fuelSavings = round(($waveHeightM * 1.8) - ($diversionDistanceNm * 0.04), 2);
        }

        return FleetWeatherRouting::create([
            'voyage_id' => $voyageId,
            'advisory_source' => 'FLEET_METOCEAN_SYSTEM',
            'weather_pattern_alert' => $weatherAlert,
            'significant_wave_height_m' => $waveHeightM,
            'recommended_speed_reduction_knots' => $speedReductionKnots,
            'diversion_distance_nm' => $diversionDistanceNm,
            'estimated_fuel_saving_mt' => $fuelSavings,
            'route_diversion_accepted' => false,
        ]);
    }

    /**
     * Terima saran deviasi rute cuaca buruk.
     */
    public function acceptWeatherDiversion(int $weatherRoutingId): FleetWeatherRouting
    {
        $advisory = FleetWeatherRouting::findOrFail($weatherRoutingId);
        $advisory->update(['route_diversion_accepted' => true]);

        // Tambahkan jarak deviasi ke rencana pelayaran
        $voyage = $advisory->voyage;
        if ($voyage) {
            $voyage->increment('planned_distance_nm', $advisory->diversion_distance_nm);
        }

        return $advisory;
    }
}
