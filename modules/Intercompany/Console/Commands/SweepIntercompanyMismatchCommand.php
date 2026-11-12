<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intercompany\Application\Services\IntercompanyMismatchSweepService;

class SweepIntercompanyMismatchCommand extends Command
{
    protected $signature = 'group:mismatch-sweep {--threshold=0 : Ambang batas toleransi selisih minor} {--period= : Filter periode format YYYY-MM}';

    protected $description = 'Penyisiran selisih saldo bilateral timbal balik dan anomali transaksi intra-grup';

    public function handle(IntercompanyMismatchSweepService $sweepService): int
    {
        $threshold = (int) $this->option('threshold');
        $period = $this->option('period') ?: null;

        $this->info('Memulai pemindaian selisih transaksi dan saldo intercompany...');

        $result = $sweepService->sweep($period, $threshold);

        $this->line("Transaksi dipindai: {$result['total_scanned_transactions']}");
        $this->line("Pinjaman dipindai: {$result['total_scanned_loans']}");
        $this->line("Diskrepansi terdeteksi: {$result['mismatch_count']}");

        if ($result['mismatch_count'] > 0) {
            $this->error("Ditemukan {$result['mismatch_count']} diskrepansi/asimetri bilateral!");

            $headers = ['Tipe', 'Kode Ref', 'Penjual/Kreditor', 'Pembeli/Debitur', 'Selisih Minor', 'Keterangan'];
            $rows = [];

            foreach ($result['mismatches'] as $m) {
                $rows[] = [
                    $m['type'],
                    $m['transaction_code'] ?? '-',
                    $m['seller_entity'],
                    $m['buyer_entity'],
                    'Rp '.number_format((float) $m['discrepancy_minor'], 0, ',', '.'),
                    $m['description'],
                ];
            }

            $this->table($headers, $rows);

            return self::FAILURE;
        }

        $this->info('Penyisiran selesai: Saldo bilateral intra-grup seimbang dan selaras.');

        return self::SUCCESS;
    }
}
