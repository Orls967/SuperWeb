<?php

declare(strict_types=1);

namespace Modules\Mining\Http\ViewModels;

use Modules\Mining\Application\Actions\RecordTireTemperaturePressureAction;
use Modules\Mining\Application\Services\ContractorFuelReconciliationService;
use Modules\Mining\Application\Services\DispatchGanttTimelineService;
use Modules\Mining\Application\Services\FleetOeeRankingService;
use Modules\Mining\Application\Services\HaulRoadFleetMaintenanceService;
use Modules\Mining\Application\Services\MatchFactorOptimizationService;
use Modules\Mining\Application\Services\PitProductionSCurveService;
use Modules\Mining\Application\Services\PitShortTermOptimizationService;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningFuelStorage;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Services\EquipmentTelemetryAnalyticsService;
use Modules\Mining\Domain\Services\MiningCostPerBcmCalculator;

/**
 * ViewModel Komposit Dashboard Operasional Pertambangan Fase 93 (T-2 HTTP Layer).
 *
 * Menyatukan data tampilan untuk 5 halaman utama Fase 93:
 * 1. Rencana Tambang, Kurva-S, Blending Bench Jangka Pendek & Biaya per BCM
 * 2. Papan Dispatch Siklus Gantt, Simulator Match Factor & Kondisi Jalan Angkut (Haul Road)
 * 3. Armada Alat Berat, Peringkat OEE, Analitika MTBF/MTTR & Evaluasi TKPH Ban
 * 4. Pengelolaan Tangki Solar, Rekonsiliasi BBM Kontraktor & Sengketa Anomali
 */
final class MiningFase93OperationsViewModel
{
    public function __construct(
        private readonly PitProductionSCurveService $sCurveService,
        private readonly PitShortTermOptimizationService $benchOptimizer,
        private readonly MiningCostPerBcmCalculator $bcmCalculator,
        private readonly MatchFactorOptimizationService $matchFactorService,
        private readonly DispatchGanttTimelineService $ganttService,
        private readonly HaulRoadFleetMaintenanceService $haulRoadService,
        private readonly FleetOeeRankingService $oeeRankingService,
        private readonly EquipmentTelemetryAnalyticsService $telemetryAnalytics,
        private readonly RecordTireTemperaturePressureAction $tireMonitorAction,
        private readonly ContractorFuelReconciliationService $fuelReconciliationService,
    ) {}

    /**
     * Menyusun konteks data halaman /mining/plans.
     *
     * @return array<string, mixed>
     */
    public function buildPlansViewData(?string $pitId, string $month): array
    {
        $pits = MiningPit::orderBy('pit_code')->get();
        $activePitId = $pitId ?: ($pits->first()?->id ? (string) $pits->first()->id : null);

        $benchBlend = null;
        if ($activePitId !== null) {
            $benchBlend = $this->benchOptimizer->optimizeBenchCut(
                pitId: $activePitId,
                targetBlendTonnageKg: 1000000,
                targetGradePpm: 16500
            );
        }

        $costAnalysis = $this->bcmCalculator->calculate(
            overburdenBcm: 320000,
            oreTonnageKg: 100000000,
            totalHaulingCostIdr: 4800000000,
            totalFuelCostIdr: 2100000000,
            totalDrillBlastCostIdr: 950000000
        );

        return [
            'sites' => MiningSite::orderBy('site_code')->get(),
            'pits' => $pits,
            'plans' => MiningPlan::with('site')->withCount('shifts')->orderByDesc('period_month')->orderBy('plan_code')->paginate(10),
            'curve' => $pitId ? $this->sCurveService->build($pitId, $month) : null,
            'pitId' => $pitId,
            'month' => $month,
            'benchBlend' => $benchBlend,
            'costAnalysis' => $costAnalysis,
        ];
    }

    /**
     * Menyusun konteks data halaman /mining/dispatch.
     *
     * @param  array{sim_trucks: int, sim_shovels: int, sim_loading: int, sim_cycle: int}  $simParams
     * @return array<string, mixed>
     */
    public function buildDispatchViewData(string $shiftDate, string $shiftCode, array $simParams): array
    {
        $pits = MiningPit::orderBy('pit_code')->get();
        $firstPitId = $pits->first()?->id ? (string) $pits->first()->id : 'PIT-DEFAULT';

        $haulRoadStatus = $this->haulRoadService->evaluateHaulRoad(
            pitId: $firstPitId,
            roadLengthMeters: 5000,
            roughnessIriMmPerKm: 3200,
            dustConcentrationPpm: 120
        );

        return [
            'sites' => MiningSite::orderBy('site_code')->get(),
            'pits' => $pits,
            'trucks' => MiningEquipment::where('type', 'HAUL_TRUCK')->orderBy('equipment_code')->get(),
            'shovels' => MiningEquipment::where('type', 'EXCAVATOR')->orderBy('equipment_code')->get(),
            'cycles' => MiningHaulCycle::with(['truck', 'shovel'])
                ->whereDate('shift_date', $shiftDate)
                ->where('shift_code', $shiftCode)
                ->orderBy('truck_id')
                ->orderBy('cycle_seq')
                ->paginate(20)
                ->withQueryString(),
            'gantt' => $this->ganttService->build($shiftDate, $shiftCode),
            'shiftDate' => $shiftDate,
            'shiftCode' => $shiftCode,
            'sim' => $this->matchFactorService->simulate(
                $simParams['sim_trucks'],
                $simParams['sim_shovels'],
                $simParams['sim_loading'],
                $simParams['sim_cycle']
            ),
            'haulRoadStatus' => $haulRoadStatus,
        ];
    }

    /**
     * Menyusun konteks data halaman /mining/equipment.
     *
     * @return array<string, mixed>
     */
    public function buildEquipmentViewData(?string $siteId): array
    {
        $equipments = MiningEquipment::with('site')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('equipment_code')
            ->paginate(20)
            ->withQueryString();

        $sampleTruck = MiningEquipment::where('type', 'HAUL_TRUCK')->orderBy('equipment_code')->first();
        $reliabilitySnapshot = null;
        $tireSnapshot = null;

        if ($sampleTruck !== null) {
            $reliabilitySnapshot = $this->telemetryAnalytics->computeReliabilityMetrics((string) $sampleTruck->id);
            $tireSnapshot = $this->tireMonitorAction->execute([
                'equipment_id' => (string) $sampleTruck->id,
                'tire_position' => 'FL',
                'pressure_psi' => 105,
                'temperature_c' => 82,
                'payload_kg' => (int) ($sampleTruck->capacity_kg ?: 90000),
                'shift_speed_meters_per_hour' => 24000,
            ]);
        }

        return [
            'sites' => MiningSite::orderBy('site_code')->get(),
            'equipments' => $equipments,
            'ranking' => $this->oeeRankingService->rank($siteId ?: null),
            'reliabilitySnapshot' => $reliabilitySnapshot,
            'tireSnapshot' => $tireSnapshot,
        ];
    }

    /**
     * Menyusun konteks data halaman /mining/fuel.
     *
     * @return array<string, mixed>
     */
    public function buildFuelViewData(?string $statusFilter, ?string $contractor, string $from, string $to): array
    {
        return [
            'cases' => MiningFuelAnomalyCase::with(['site', 'equipment'])
                ->when($statusFilter, fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('shift_date')
                ->orderBy('case_number')
                ->paginate(15)
                ->withQueryString(),
            'totalHeldIdr' => (int) MiningFuelAnomalyCase::where('status', FuelAnomalyCaseStatus::PAYMENT_HELD->value)->sum('held_amount_idr'),
            'totalDeductedIdr' => (int) MiningFuelAnomalyCase::where('status', FuelAnomalyCaseStatus::RESOLVED_DEDUCTED->value)->sum('held_amount_idr'),
            'storages' => MiningFuelStorage::orderBy('storage_code')->get(),
            'trucks' => MiningEquipment::orderBy('equipment_code')->get(),
            'summary' => $contractor
                ? $this->fuelReconciliationService->summarize($contractor, $from, $to)
                : null,
        ];
    }
}
