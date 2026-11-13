<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Contracts\StoreProductStockPort;
use Modules\Wms\Application\Services\RoboticsAuditService;
use Modules\Wms\Contracts\WarehouseValuationReader;

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

        // 1. Tidak ada stok bin negatif (qty maupun qty_base).
        $negative = DB::table('wms_bin_stocks')
            ->where(function ($q): void {
                $q->where('qty', '<', 0)->orWhere('qty_base', '<', 0);
            })
            ->count();
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
            $cached = app(StoreProductStockPort::class)->getCachedStock((int) $productId);

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

        // 4. Rekonsiliasi nilai subledger persediaan vs saldo buku besar wms:inventory:*:IDR (T-10).
        $valuations = app(WarehouseValuationReader::class)->getAllWarehousesValuationIdr();
        foreach ($valuations as $val) {
            if ($val['discrepancy_idr'] !== 0) {
                $hasDiscrepancy = true;
                $rows[] = [
                    'ledger:'.$val['warehouse_code'],
                    (string) $val['subledger_value_idr'],
                    (string) $val['ledger_balance_idr'],
                    (string) $val['discrepancy_idr'],
                    'SELISIH',
                ];
            }
        }

        // 5. Integritas robotika gudang (Fase 279): dual active zone lock & orphan missions.
        if (Schema::hasTable('wms_robot_zone_locks')) {
            $roboticsViolations = app(RoboticsAuditService::class)->findViolations();
            foreach ($roboticsViolations as $idx => $violation) {
                $hasDiscrepancy = true;
                $rows[] = ['robotics:#'.($idx + 1), '0', '1', $violation, 'SELISIH'];
            }
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
