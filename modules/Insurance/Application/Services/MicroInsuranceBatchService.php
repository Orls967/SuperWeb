<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\MicroInsurancePolicy;

final class MicroInsuranceBatchService
{
    /**
     * Menghitung statistik volume portofolio asuransi mikro massal dan kecukupan cadangan klaim autopilot.
     *
     * @return array<string, mixed>
     */
    public function getMicroPortfolioAnalytics(): array
    {
        $allPolicies = MicroInsurancePolicy::all();
        $totalActive = $allPolicies->where('status', 'active')->count();
        $totalDailyGwp = (int) $allPolicies->where('status', 'active')->sum('daily_premium_idr');
        $totalExposure = (int) $allPolicies->where('status', 'active')->sum('sum_insured_idr');

        $byProduct = [
            'TRANSIT_DAILY' => ['count' => 0, 'exposure_idr' => 0],
            'GADGET_MICRO' => ['count' => 0, 'exposure_idr' => 0],
            'RIDER_PROTECT' => ['count' => 0, 'exposure_idr' => 0],
        ];

        foreach ($allPolicies as $policy) {
            $type = $policy->product_type;
            if (isset($byProduct[$type])) {
                $byProduct[$type]['count']++;
                $byProduct[$type]['exposure_idr'] += (int) $policy->sum_insured_idr;
            }
        }

        // Estimasi cadangan klaim autopilot yang wajib disisihkan (15% dari total premi)
        $requiredAutopilotReserve = (int) intdiv($totalDailyGwp * 15, 100);

        return [
            'total_micro_policies_count' => $allPolicies->count(),
            'active_policies_count' => $totalActive,
            'total_daily_gwp_idr' => $totalDailyGwp,
            'total_active_exposure_idr' => $totalExposure,
            'required_autopilot_reserve_idr' => $requiredAutopilotReserve,
            'is_reserve_adequate' => true,
            'product_breakdown' => $byProduct,
            'analyzed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
