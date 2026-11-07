<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ScanExpiredWarehouseReceiptsAction;

final class ScanExpiredWarehouseReceiptsCommand extends Command
{
    protected $signature = 'mining:receipt-expiry-scan {date? : Tanggal referensi kadaluarsa YYYY-MM-DD}';

    protected $description = 'Memindai resi gudang (SRG) logam yang jatuh tempo untuk memicu default/eksekusi sita jaminan';

    public function handle(ScanExpiredWarehouseReceiptsAction $action): int
    {
        $refDate = $this->argument('date') ? (string) $this->argument('date') : null;
        $this->info('Memulai pemindaian jatuh tempo Resi Gudang Logam (SRG)...');

        $result = $action->execute($refDate);

        $this->table(
            ['Metrik Pemindaian Resi Gudang', 'Jumlah Lot / Dokumen'],
            [
                ['Total Resi Gudang Dipindai', $result['scanned']],
                ['Resi Gudang Kedaluwarsa (Expired)', $result['expired']],
                ['Resi Tergadai Wanprestasi (Defaulted SCF)', $result['defaulted']],
            ]
        );

        $this->info('Pemindaian siklus hidup resi gudang selesai.');

        return self::SUCCESS;
    }
}
