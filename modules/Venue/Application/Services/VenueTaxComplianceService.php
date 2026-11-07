<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class VenueTaxComplianceService
{
    /**
     * Menghitung kewajiban Pajak Barang dan Jasa Tertentu (PBJT) Hiburan daerah.
     * Tarif standar: 10% s.d. 15% (Badung/Bali atau DKI Jakarta).
     *
     * @return array{
     *     gross_ticket_sales_idr: int,
     *     tax_rate_bps: int,
     *     statutory_pbjt_tax_idr: int,
     *     net_ticket_revenue_idr: int,
     *     withholding_account: string
     * }
     */
    public function computePbjtEntertainmentTax(
        EntertainmentEvent $event,
        int $taxRateBps = 1000 // 10.00%
    ): array {
        $grossSales = (int) EntertainmentTicket::where('event_id', $event->id)
            ->where('status', '!=', 'refunded')
            ->sum('price_paid_idr');

        $taxAmount = (int) round(($grossSales * $taxRateBps) / 10000);
        $netSales = $grossSales - $taxAmount;

        return [
            'gross_ticket_sales_idr' => $grossSales,
            'tax_rate_bps' => $taxRateBps,
            'statutory_pbjt_tax_idr' => $taxAmount,
            'net_ticket_revenue_idr' => $netSales,
            'withholding_account' => 'ven:entertainment_tax_payable:IDR',
        ];
    }
}
