<?php

declare(strict_types=1);

namespace Modules\Procurement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Procurement\Application\Services\ReceivingService;
use Modules\Procurement\Domain\Models\ApEntry;

/**
 * 34.8 Audit subledger AP & GR/IR.
 *
 * Verifikasi (agregat SQL, tanpa memuat seluruh baris ke memori):
 * 1. Σ ap_entry per supplier == saldo ledger `ap:supplier:{id}:IDR`.
 * 2. GR/IR = 0 untuk seluruh PO berstatus received (sudah diinvois).
 *
 * Exit 1 bila ada selisih.
 */
class AuditProcurementCommand extends Command
{
    protected $signature = 'proc:audit';

    protected $description = 'Audit subledger AP dan GR/IR terhadap ledger (harus 0 selisih)';

    public function handle(ReceivingService $service): int
    {
        $this->info('Memulai audit subledger pembelian...');

        $rows = [];
        $hasDiscrepancy = false;

        // 1. Subledger AP per pemasok == ledger.
        $apBySupplier = ApEntry::query()
            ->where('kind', 'ap')
            ->selectRaw("supplier_id, SUM(CASE direction WHEN 'credit' THEN amount_idr ELSE -amount_idr END) as ap_delta")
            ->groupBy('supplier_id')
            ->pluck('ap_delta', 'supplier_id');

        $paymentsBySupplier = ApEntry::query()
            ->where('kind', 'payment')
            ->selectRaw("supplier_id, SUM(CASE direction WHEN 'debit' THEN amount_idr ELSE -amount_idr END) as paid")
            ->groupBy('supplier_id')
            ->pluck('paid', 'supplier_id');

        foreach ($apBySupplier as $supplierId => $apDelta) {
            $balances = $service->accountBalances((string) $supplierId);
            // Konvensi ledger proyek: posting KREDIT = saldo negatif (lihat
            // PostingEntryDTO::forAccount(..., negated)). Jadi saldo ledger
            // = pembayaran(debit, positif) − tagihan(credit).
            $expected = (int) ($paymentsBySupplier[$supplierId] ?? 0) - (int) $apDelta;
            $actual = (int) $balances['ap_idr'];

            if ($expected !== $actual) {
                $hasDiscrepancy = true;
                $rows[] = ['ap:supplier:'.$supplierId, number_format($expected), number_format($actual), number_format($expected - $actual), 'SELISIH'];
            }
        }

        // 2. GR/IR = 0 untuk PO yang sudah diterima seluruhnya (barang + invoice beres).
        $unmatched = DB::table('prc_receiving_reports as grn')
            ->join('prc_purchase_orders as po', 'po.id', '=', 'grn.po_id')
            ->where('po.status', 'received')
            ->whereNotIn('grn.status', ['accepted', 'closed'])
            ->count();

        if ($unmatched > 0) {
            $hasDiscrepancy = true;
            $rows[] = ['gr_ir', '0 GRN belum diakui', $unmatched.' GRN', (string) $unmatched, 'SELISIH'];
        }

        $this->table(['Akun/Sumber', 'Subledger', 'Ledger', 'Selisih', 'Status'], $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows);

        if ($hasDiscrepancy) {
            $this->error('DITEMUKAN SELISIH PADA SUBLEDGER PEMBELIAN.');

            return self::FAILURE;
        }

        $this->info('✓ proc:audit selesai: subledger AP & GR/IR sinkron dengan ledger (0 selisih).');

        return self::SUCCESS;
    }
}
