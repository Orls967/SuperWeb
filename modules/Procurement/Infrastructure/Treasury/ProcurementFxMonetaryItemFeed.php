<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Treasury;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Treasury\Contracts\FxMonetaryItemFeed;

/**
 * Pengadaptasi aditif lintas-modul (D48-11b):
 * Menyediakan daftar pos moneter valuta asing terbuka (utang usaha / komitmen PO impor)
 * dari modul Procurement untuk agregasi revaluasi kurs akhir periode (PSAK 10) di modul Treasury.
 */
final class ProcurementFxMonetaryItemFeed implements FxMonetaryItemFeed
{
    /**
     * @return iterable<array{
     *     source_module: string,
     *     source_entity_id: string,
     *     currency: string,
     *     balance_minor: int,
     *     carrying_functional_idr: int,
     *     item_type: string,
     *     is_asset: bool,
     *     lot_date: string
     * }>
     */
    public function items(string $period): iterable
    {
        $items = [];

        if (! Schema::hasTable('prc_purchase_orders')) {
            return $items;
        }

        $hasCurrency = Schema::hasColumn('prc_purchase_orders', 'currency');
        if (! $hasCurrency) {
            return $items;
        }

        $orders = DB::table('prc_purchase_orders')
            ->where('currency', '!=', 'IDR')
            ->whereIn('status', ['APPROVED', 'ISSUED', 'PARTIALLY_DELIVERED', 'INVOICED', 'approved', 'issued'])
            ->limit(250)
            ->get();

        foreach ($orders as $order) {
            $foreignMinor = (int) ($order->foreign_amount_minor ?? $order->total_amount ?? 0);
            $carryingIdr = (int) ($order->total_amount_idr ?? $order->total_amount ?? 0);

            if ($foreignMinor <= 0 || $carryingIdr <= 0) {
                continue;
            }

            $lotDate = isset($order->order_date)
                ? substr((string) $order->order_date, 0, 10)
                : (isset($order->created_at) ? substr((string) $order->created_at, 0, 10) : $period.'-01');

            $items[] = [
                'source_module' => 'Procurement',
                'source_entity_id' => (string) ($order->po_number ?? ('PO-'.$order->id)),
                'currency' => strtoupper((string) $order->currency),
                'balance_minor' => $foreignMinor,
                'carrying_functional_idr' => $carryingIdr,
                'item_type' => 'accounts_payable_po',
                'is_asset' => false,
                'lot_date' => $lotDate,
            ];
        }

        return $items;
    }
}
