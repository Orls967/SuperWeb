<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyIndicators;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;

final class GenerateInpatientSummaryReportAction
{
    private BedEfficiencyIndicators $efficiencyIndicators;

    public function __construct(BedEfficiencyIndicators $efficiencyIndicators)
    {
        $this->efficiencyIndicators = $efficiencyIndicators;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $totalBeds, int $periodDays = 30): array
    {
        $activeAdmissionsCount = Admission::whereIn('status', ['ADMITTED', 'BED_OCCUPIED', 'ACTIVE'])->count();
        $dischargedCount = Admission::where('status', 'DISCHARGED')->count();

        $occupiedBedsCount = HospitalBed::where('status', 'OCCUPIED')->count();
        $bedDayCount = max(1, $occupiedBedsCount * $periodDays);
        $patientDischargeCount = max(1, $dischargedCount);

        $indicators = $this->efficiencyIndicators->calculate(
            bedCount: max(1, $totalBeds),
            periodDays: $periodDays,
            bedDayCount: $bedDayCount,
            patientDischargeCount: $patientDischargeCount
        );

        return [
            'period_days' => $periodDays,
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupiedBedsCount,
            'active_admissions' => $activeAdmissionsCount,
            'discharged_patients' => $dischargedCount,
            'bor_percent' => $indicators->bor,
            'alos_days' => $indicators->alos,
            'toi_days' => $indicators->toi,
            'bto_times' => $indicators->bto,
            'barber_johnson_status' => $indicators->barberJohnsonEfficiency,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
