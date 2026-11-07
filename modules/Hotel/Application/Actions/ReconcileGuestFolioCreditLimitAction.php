<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Tindakan Audit Batas Kredit Folio Tamu Berjalan & Peringatan Over-Limit.
 */
final class ReconcileGuestFolioCreditLimitAction
{
    /**
     * @return array{folio_id: string, current_balance_idr: int, credit_limit_idr: int, is_over_limit: bool}
     */
    public function execute(string|int $folioId, int $creditLimitIdr = 5000000): array
    {
        $folio = HotelFolio::findOrFail((string) $folioId);

        $charges = (int) HotelFolioLine::where('folio_id', $folio->id)
            ->where('amount_idr', '>', 0)
            ->sum('amount_idr');

        $payments = (int) abs(HotelFolioLine::where('folio_id', $folio->id)
            ->where('amount_idr', '<', 0)
            ->sum('amount_idr'));

        $netBalance = $charges - $payments;
        $isOver = $netBalance > $creditLimitIdr;

        return [
            'folio_id' => (string) $folio->id,
            'current_balance_idr' => $netBalance,
            'credit_limit_idr' => $creditLimitIdr,
            'is_over_limit' => $isOver,
        ];
    }
}
