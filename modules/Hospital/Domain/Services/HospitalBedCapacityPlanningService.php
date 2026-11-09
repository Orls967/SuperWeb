<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;

final class HospitalBedCapacityPlanningService
{
    /**
     * Memproyeksikan kebutuhan ranjang bangsal untuk 7 hari ke depan
     * berdasarkan rata-rata moving average admisi harian dan laju pemulangan.
     *
     * @return array<string, mixed>
     */
    public function forecastDemand(): array
    {
        $beds = HospitalBed::all();
        $totalBeds = $beds->count();
        $occupiedBeds = $beds->where('status', 'OCCUPIED')->count();
        $borCurrent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 2) : 0.0;

        $admissionsLast7Days = Admission::where('admission_time', '>=', CarbonImmutable::now()->subDays(7))->count();
        $dischargesLast7Days = Admission::where('discharge_time', '>=', CarbonImmutable::now()->subDays(7))->count();

        $dailyAdmissionAvg = round($admissionsLast7Days / 7, 2);
        $dailyDischargeAvg = round($dischargesLast7Days / 7, 2);
        $netDailyChange = $dailyAdmissionAvg - $dailyDischargeAvg;

        $projectedOccupancy7Days = max(0, min($totalBeds, (int) round($occupiedBeds + ($netDailyChange * 7))));
        $projectedBor7Days = $totalBeds > 0 ? round(($projectedOccupancy7Days / $totalBeds) * 100, 2) : 0.0;

        $recommendation = match (true) {
            $projectedBor7Days >= 90.0 => 'CRITICAL_BOTTLENECK_ACTIVATE_SURGE_BEDS',
            $projectedBor7Days >= 80.0 => 'HIGH_OCCUPANCY_ACCELERATE_DISCHARGES',
            $projectedBor7Days <= 60.0 => 'LOW_OCCUPANCY_ALLOW_SCHEDULED_MAINTENANCE',
            default => 'BALANCED_CAPACITY',
        };

        return [
            'total_beds' => $totalBeds,
            'currently_occupied' => $occupiedBeds,
            'current_bor_pct' => $borCurrent,
            'daily_admission_avg' => $dailyAdmissionAvg,
            'daily_discharge_avg' => $dailyDischargeAvg,
            'projected_occupied_7d' => $projectedOccupancy7Days,
            'projected_bor_7d_pct' => $projectedBor7Days,
            'capacity_recommendation' => $recommendation,
            'calculated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
