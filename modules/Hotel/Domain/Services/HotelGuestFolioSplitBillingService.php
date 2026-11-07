<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Layanan Pemisahan Tagihan Folio Tamu (Personal vs Corporate City Ledger).
 */
final class HotelGuestFolioSplitBillingService
{
    /**
     * @param  list<string>  $corporateLineTypes
     * @return array{personal_subtotal_idr: int, corporate_subtotal_idr: int, total_balance_idr: int}
     */
    public function calculateSplitSummary(
        string|int $folioId,
        array $corporateLineTypes = ['ROOM_CHARGE', 'MEETING_ROOM']
    ): array {
        $lines = HotelFolioLine::where('folio_id', (string) $folioId)->get();

        $personalSubtotal = 0;
        $corporateSubtotal = 0;

        foreach ($lines as $line) {
            $amount = (int) $line->amount_idr;
            if (in_array($line->line_type, $corporateLineTypes, true)) {
                $corporateSubtotal += $amount;
            } else {
                $personalSubtotal += $amount;
            }
        }

        return [
            'personal_subtotal_idr' => $personalSubtotal,
            'corporate_subtotal_idr' => $corporateSubtotal,
            'total_balance_idr' => $personalSubtotal + $corporateSubtotal,
        ];
    }
}
