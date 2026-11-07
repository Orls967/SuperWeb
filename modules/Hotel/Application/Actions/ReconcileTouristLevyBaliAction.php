<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelApoaReport;

/**
 * Tindakan Rekonsiliasi & Audit Pembayaran Pungutan Wisatawan Asing (Bali Tourist Levy / PWA).
 */
final class ReconcileTouristLevyBaliAction
{
    /**
     * @return array{total_foreign_guests: int, levy_paid_count: int, levy_unpaid_count: int, compliance_rate_bps: int}
     */
    public function execute(string $startDate, string $endDate): array
    {
        $reports = HotelApoaReport::whereBetween('check_in_date', [$startDate, $endDate])->get();
        $totalForeign = $reports->count();

        if ($totalForeign === 0) {
            return [
                'total_foreign_guests' => 0,
                'levy_paid_count' => 0,
                'levy_unpaid_count' => 0,
                'compliance_rate_bps' => 10000,
            ];
        }

        $paidCount = $reports->where('is_bali_pwa_paid', true)->count();
        $unpaidCount = $totalForeign - $paidCount;
        $complianceBps = (int) round(($paidCount / $totalForeign) * 10000);

        return [
            'total_foreign_guests' => $totalForeign,
            'levy_paid_count' => $paidCount,
            'levy_unpaid_count' => $unpaidCount,
            'compliance_rate_bps' => $complianceBps,
        ];
    }
}
