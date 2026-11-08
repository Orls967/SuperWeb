<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Perencanaan Docking & Survey Statutoria Berkala Galangan Kapal.
 * Standar: IACS (International Association of Classification Societies) Unified Requirements UR Z7.
 */
final class VesselDrydockingAndSurveyPlanningService
{
    /**
     * Rencanakan docking berkala kapal (Drydocking & Special Survey Specification).
     *
     * @param  string  $shipyardName  Nama galangan kapal (misal PT Dok & Perkapalan Surabaya)
     * @param  int  $dockingDurationDays  Estimasi hari docking (misal 18 hari)
     * @param  float  $steelRenewalTons  Penggantian pelat baja lambung aus (MT)
     * @param  float  $hullBlastingAreaM2  Luas permukaan lambung yang diblast (SA 2.5)
     * @param  bool  $tailshaftWithdrawalDue  Survey poros baling-baling (Tailshaft) jatuh tempo
     * @return array{
     *     vessel_id: int,
     *     vessel_name: string,
     *     shipyard: string,
     *     scheduled_date: string,
     *     duration_days: int,
     *     docking_type: string,
     *     estimated_shipyard_cost_usd: float,
     *     work_specifications: array<int, string>,
     *     status: string
     * }
     */
    public function planDrydockSpecification(
        int $vesselId,
        string $shipyardName,
        CarbonImmutable $scheduledDockingDate,
        int $dockingDurationDays = 18,
        float $steelRenewalTons = 15.0,
        float $hullBlastingAreaM2 = 4500.0,
        bool $tailshaftWithdrawalDue = false
    ): array {
        $vessel = FleetVessel::with('classification')->findOrFail($vesselId);

        // Biaya dasar docking harian (graving dock dues)
        $dailyDockDues = 4500.0;
        $totalDockDues = $dockingDurationDays * $dailyDockDues;

        // Biaya pekerjaan perbaikan lambung:
        // Steel renewal: ~$3,200 per MT (material + fitting + welding + class NDT testing)
        $steelCost = $steelRenewalTons * 3200.0;

        // Hydro-blasting & Antifouling paint application: ~$22 per m2
        $blastingAndPaintCost = $hullBlastingAreaM2 * 22.0;

        // Tailshaft & Propeller survey
        $tailshaftCost = $tailshaftWithdrawalDue ? 45000.0 : 12000.0;

        // Overhaul katup laut (sea valves) & jangkar/rantai
        $ancillaryCost = 28000.0;

        $totalEstimatedCost = $totalDockDues + $steelCost + $blastingAndPaintCost + $tailshaftCost + $ancillaryCost;

        $specs = [
            "Pembersihan lambung bawah air dan grit blasting SA 2.5 seluas {$hullBlastingAreaM2} m2.",
            'Aplikasi cat antifouling silyl premium 3 lapis untuk interval 60 bulan.',
            "Penggantian pelat baja lambung (steel renewal) sebesar {$steelRenewalTons} MT dengan pengujian ultrasonik NDT.",
            'Overhaul 100% sea chest valves dan pengujian tekanan hidrolik.',
            'Pemeriksaan celah bantalan kemudi (rudder pintle clearance) dan pengelasan anoda korban (zinc anodes).',
        ];

        if ($tailshaftWithdrawalDue) {
            $specs[] = 'Pencabutan poros baling-baling (Tailshaft survey) dan inspeksi keretakan dye-penetrant.';
        }

        return [
            'vessel_id' => $vessel->id,
            'vessel_name' => $vessel->vessel_name,
            'shipyard' => $shipyardName,
            'scheduled_date' => $scheduledDockingDate->toDateString(),
            'duration_days' => $dockingDurationDays,
            'docking_type' => $tailshaftWithdrawalDue ? 'SPECIAL_SURVEY_DRYDOCK' : 'INTERMEDIATE_DRYDOCK',
            'estimated_shipyard_cost_usd' => round($totalEstimatedCost, 2),
            'work_specifications' => $specs,
            'status' => 'SPECIFICATION_DRAFTED',
        ];
    }
}
