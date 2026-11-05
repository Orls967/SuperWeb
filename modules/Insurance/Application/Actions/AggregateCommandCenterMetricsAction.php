<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Domain\Models\InsuranceCommandSnapshot;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\MicroInsurancePolicy;
use Modules\Insurance\Domain\Models\TakafulParticipant;

final class AggregateCommandCenterMetricsAction
{
    /**
     * Mengagregasi metrik C-suite portofolio operasional asuransi lintas produk (Fase 160.4).
     */
    public function execute(string $snapshotDate): InsuranceCommandSnapshot
    {
        $date = CarbonImmutable::parse($snapshotDate);

        // Agregasi GWP (Gross Written Premium)
        $lifeGwp = (int) LifePolicy::sum('premium_annual_idr');
        $healthGwp = (int) HealthPolicy::sum('premium_annual_idr');
        $takafulGwp = (int) TakafulParticipant::sum('contribution_annual_idr');
        $agriGwp = (int) AgriInsurancePolicy::sum('premium_annual_idr');
        $microGwp = (int) MicroInsurancePolicy::sum('daily_premium_idr');

        $totalGwp = $lifeGwp + $healthGwp + $takafulGwp + $agriGwp + $microGwp;
        // Asumsi Net Earned Premium = 85% dari GWP
        $netEarned = (int) intdiv($totalGwp * 85, 100);

        // Klaim terjadi estimasi 55% dari net earned
        $claimsIncurred = (int) intdiv($netEarned * 55, 100);
        $lossRatioBps = $netEarned > 0 ? (int) intdiv($claimsIncurred * 10000, $netEarned) : 5500;

        // Solvabilitas RBC (Risk-Based Capital) target 245% (24.500 bps)
        $solvencyRbcBps = 24500;
        $technicalReserve = (int) intdiv($totalGwp * 65, 100);
        $reinsuranceRecoverable = (int) intdiv($claimsIncurred * 20, 100);

        return InsuranceCommandSnapshot::updateOrCreate(
            ['snapshot_date' => $date->toDateString()],
            [
                'gross_written_premium_idr' => $totalGwp,
                'net_earned_premium_idr' => $netEarned,
                'claims_incurred_idr' => $claimsIncurred,
                'loss_ratio_bps' => $lossRatioBps,
                'solvency_rbc_bps' => $solvencyRbcBps,
                'total_technical_reserve_idr' => $technicalReserve,
                'reinsurance_recoverable_idr' => $reinsuranceRecoverable,
            ]
        );
    }
}
