<?php

declare(strict_types=1);

namespace Modules\Trade\Infrastructure\Treasury;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Treasury\Contracts\FxMonetaryItemFeed;

/**
 * Pengadaptasi aditif lintas-modul (D48-11b):
 * Menyediakan daftar pos moneter valuta asing terbuka dari transaksi impor/ekspor
 * modul Trade untuk agregasi revaluasi kurs akhir periode (PSAK 10) di modul Treasury.
 */
final class TradeFxMonetaryItemFeed implements FxMonetaryItemFeed
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

        if (Schema::hasTable('trd_import_orders') && Schema::hasColumn('trd_import_orders', 'currency')) {
            $imports = DB::table('trd_import_orders')
                ->where('currency', '!=', 'IDR')
                ->whereNotIn('status', ['CANCELLED', 'CLOSED', 'cancelled', 'closed'])
                ->limit(250)
                ->get();

            foreach ($imports as $imp) {
                $foreignMinor = (int) ($imp->fob_foreign_minor ?? $imp->foreign_amount_minor ?? 0);
                $carryingIdr = (int) ($imp->total_landed_cost_idr ?? $imp->fob_amount_idr ?? 0);

                if ($foreignMinor <= 0 || $carryingIdr <= 0) {
                    continue;
                }

                $lotDate = isset($imp->created_at) ? substr((string) $imp->created_at, 0, 10) : $period.'-01';

                $items[] = [
                    'source_module' => 'Trade',
                    'source_entity_id' => (string) ($imp->order_number ?? ('IMP-'.$imp->id)),
                    'currency' => strtoupper((string) $imp->currency),
                    'balance_minor' => $foreignMinor,
                    'carrying_functional_idr' => $carryingIdr,
                    'item_type' => 'trade_import_payable',
                    'is_asset' => false,
                    'lot_date' => $lotDate,
                ];
            }
        }

        if (Schema::hasTable('trd_export_shipments') && Schema::hasColumn('trd_export_shipments', 'currency')) {
            $exports = DB::table('trd_export_shipments')
                ->where('currency', '!=', 'IDR')
                ->whereNotIn('status', ['SETTLED', 'CANCELLED', 'settled', 'cancelled'])
                ->limit(250)
                ->get();

            foreach ($exports as $exp) {
                $foreignMinor = (int) ($exp->invoice_foreign_minor ?? $exp->foreign_amount_minor ?? 0);
                $carryingIdr = (int) ($exp->invoice_amount_idr ?? 0);

                if ($foreignMinor <= 0 || $carryingIdr <= 0) {
                    continue;
                }

                $lotDate = isset($exp->created_at) ? substr((string) $exp->created_at, 0, 10) : $period.'-01';

                $items[] = [
                    'source_module' => 'Trade',
                    'source_entity_id' => (string) ($exp->shipment_number ?? ('EXP-'.$exp->id)),
                    'currency' => strtoupper((string) $exp->currency),
                    'balance_minor' => $foreignMinor,
                    'carrying_functional_idr' => $carryingIdr,
                    'item_type' => 'trade_export_receivable',
                    'is_asset' => true,
                    'lot_date' => $lotDate,
                ];
            }
        }

        return $items;
    }
}
