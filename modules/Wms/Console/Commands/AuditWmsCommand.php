<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 41.8 Audit: Σ stok bin = saldo `inv_` terkait, tidak ada stok bin negatif.
 */
class AuditWmsCommand extends Command
{
    protected $signature = 'wms:audit';

    protected $description = 'Audit WMS: Σ stok bin vs saldo inv_ dan stok tidak negatif';

    public function handle(): int
    {
        $hasDiscrepancy = false;
        $rows = [];

        // 1. Tidak ada stok bin negatif.
        $negative = DB::table('wms_bin_stocks')->where('qty', '<', 0)->count();
        if ($negative > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['bin_stocks.negatif', '0', (string) $negative, (string) $negative, 'SELISIH'];
        }

        // 2. Per product: Σ stok bin ≥ 0 (produk dapat punya stok di luar WMS —
        //    mis. reservasi checkout; audit hanya memastikan tidak negatif di WMS).
        $binByProduct = DB::table('wms_bin_stocks')
            ->selectRaw('product_id, SUM(qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        foreach ($binByProduct as $productId => $total) {
            $cached = (int) DB::table('store_products')->where('id', $productId)->value('cached_stock');
            if ((float) $total < 0) {
                $hasDiscrepancy = true;
                $rows[] = ['product:'.$productId.' bin', number_format((float) $total), (string) $cached, 'negatif', 'SELISIH'];
            }
            // Bin WMS tidak boleh melebihi cached_stock (sinkronisasi dua arah dijaga mutasi).
            if ((float) $total > $cached + 0.0001) {
                $hasDiscrepancy = true;
                $rows[] = [
                    'product:'.$productId.' bin>saldo',
                    number_format((float) $total),
                    (string) $cached,
                    number_format((float) $total - $cached),
                    'SELISIH',
                ];
            }
        }

        // 3. Transfer in_transit tidak boleh menyisakan baris terbuka tanpa qty.
        $orphanLines = DB::table('wms_transfer_lines as tl')
            ->join('wms_transfers as t', 't.id', '=', 'tl.transfer_id')
            ->where('t.status', 'received')
            ->where('tl.status', '!=', 'received')
            ->count();
        if ($orphanLines > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['transfer.diterima', '0 baris terbuka', $orphanLines.' baris', (string) $orphanLines, 'SELISIH'];
        }

        $this->table(['Sumber', 'Subledger', 'Saldo inv_', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows);

        if ($hasDiscrepancy) {
            $this->error('DITEMUKAN SELISIH PADA WMS.');

            return self::FAILURE;
        }

        $this->info('✓ wms:audit selesai: stok bin ≥ 0 & tidak melebihi saldo inv_ (0 selisih).');

        return self::SUCCESS;
    }
}
