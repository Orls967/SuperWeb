<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelCityLedgerBadDebtProvisionEngine
{
    /**
     * Menghitung cadangan kerugian penurunan nilai (CKPN / Bad Debt Provision) piutang City Ledger korporat berdasarkan PSAK 71.
     *
     * @return array{total_portfolio_ar_idr: int, current_bucket_provision_idr: int, watch_bucket_provision_idr: int, doubtful_bucket_provision_idr: int, total_provision_idr: int, provision_ratio_pct: float}
     */
    public function computePortfolioProvision(
        int $currentArIdr,
        int $watchAr30To60Idr,
        int $doubtfulArOver60Idr
    ): array {
        // Bobot CKPN: Lancar 0.5%, Perhatian 2.0%, Kurang Lancar/Macet 25.0%
        $provCurrent = (int) round(($currentArIdr * 50) / 10000);
        $provWatch = (int) round(($watchAr30To60Idr * 200) / 10000);
        $provDoubtful = (int) round(($doubtfulArOver60Idr * 2500) / 10000);

        $totalAr = $currentArIdr + $watchAr30To60Idr + $doubtfulArOver60Idr;
        $totalProv = $provCurrent + $provWatch + $provDoubtful;
        $ratio = $totalAr > 0 ? round(($totalProv / $totalAr) * 100, 2) : 0.0;

        return [
            'total_portfolio_ar_idr' => $totalAr,
            'current_bucket_provision_idr' => $provCurrent,
            'watch_bucket_provision_idr' => $provWatch,
            'doubtful_bucket_provision_idr' => $provDoubtful,
            'total_provision_idr' => $totalProv,
            'provision_ratio_pct' => $ratio,
        ];
    }
}
