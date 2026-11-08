<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\RecognizeLoyaltyBreakageRevenueAction;

final class HotelExpireTravelPassBatchesCommand extends Command
{
    protected $signature = 'hotel:expire-travel-pass-batches {--pass= : ID Travel Pass spesifik}';

    protected $description = 'Memeriksa batch poin anggota yang melewati tanggal kadaluwarsa dan mengakui breakage';

    public function handle(RecognizeLoyaltyBreakageRevenueAction $action): int
    {
        $passId = $this->option('pass');
        $this->info('Memulai pemindaian batch kadaluwarsa...');

        $result = $action->execute($passId);

        $this->info('✓ Pemindaian selesai.');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Batch Dipindai', $result->totalBatchesScanned],
                ['Batch Kadaluwarsa', $result->totalExpiredBatches],
                ['Poin Kadaluwarsa', number_format($result->totalExpiredPts)],
                ['Pendapatan Breakage (IDR)', number_format($result->breakageRevenueIdr)],
            ]
        );

        return self::SUCCESS;
    }
}
