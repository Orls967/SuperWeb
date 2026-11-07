<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;

final class TaxPbjtReconciliationService
{
    public const PBJT_TAX_RATE_BPS = 1000; // 10.00% PBJT Kesenian & Hiburan

    /**
     * Reconcile municipal entertainment tax (Pajak Barang dan Jasa Tertentu - PBJT) for an event.
     *
     * @return array{
     *     event_code: string,
     *     ticket_gross_idr: int,
     *     ticket_pbjt_tax_idr: int,
     *     table_gross_idr: int,
     *     table_pbjt_tax_idr: int,
     *     total_taxable_base_idr: int,
     *     total_pbjt_payable_idr: int,
     *     is_reconciled: bool
     * }
     */
    public function reconcileEventPbjt(EntertainmentEvent $event): array
    {
        $ticketGross = (int) EntertainmentTicket::where('event_id', $event->id)
            ->where('status', '!=', 'refunded')
            ->sum('price_paid_idr');

        $tableGross = (int) EntertainmentTableBooking::where('event_id', $event->id)
            ->whereIn('status', ['settled', 'no_show_forfeited'])
            ->sum('minimum_spend_idr');

        // G7-D32: Pajak per baris dibulatkan ke bawah
        $ticketTax = (int) floor(($ticketGross * self::PBJT_TAX_RATE_BPS) / 10000);
        $tableTax = (int) floor(($tableGross * self::PBJT_TAX_RATE_BPS) / 10000);

        $totalBase = $ticketGross + $tableGross;
        $totalTaxPayable = $ticketTax + $tableTax;

        return [
            'event_code' => $event->event_code,
            'ticket_gross_idr' => $ticketGross,
            'ticket_pbjt_tax_idr' => $ticketTax,
            'table_gross_idr' => $tableGross,
            'table_pbjt_tax_idr' => $tableTax,
            'total_taxable_base_idr' => $totalBase,
            'total_pbjt_payable_idr' => $totalTaxPayable,
            'is_reconciled' => true,
        ];
    }
}
