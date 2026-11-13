<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pelacakan jejak lot (lot traceability) lintas penerimaan GRN, bin stock, & reservasi.
 */
final class WmsLotTraceabilityService
{
    /**
     * @return array{
     *     lot_number: string,
     *     receipts: list<object>,
     *     bin_positions: list<object>,
     *     reservations: list<object>,
     *     total_on_hand_base: int
     * }
     */
    public function traceLot(string $lotNumber): array
    {
        $receipts = DB::table('wms_receiving_receipts')
            ->where('lot_number', $lotNumber)
            ->orderBy('id')
            ->get()
            ->all();

        $binPositions = DB::table('wms_bin_stocks')
            ->where('lot_number', $lotNumber)
            ->orderBy('id')
            ->get()
            ->all();

        $reservations = DB::table('wms_stock_reservations')
            ->where('lot_number', $lotNumber)
            ->orderBy('id')
            ->get()
            ->all();

        $totalOnHand = 0;
        foreach ($binPositions as $pos) {
            $totalOnHand += (int) $pos->qty_base;
        }

        return [
            'lot_number' => $lotNumber,
            'receipts' => $receipts,
            'bin_positions' => $binPositions,
            'reservations' => $reservations,
            'total_on_hand_base' => $totalOnHand,
        ];
    }
}
