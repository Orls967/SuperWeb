<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ScanExpiringPermitsAction;

class ScanPermitExpiryCommand extends Command
{
    protected $signature = 'mining:permit-expiry-scan';

    protected $description = 'Pindai masa berlaku izin kerja K3 (PTW) dan ubah otomatis izin kedaluwarsa menjadi EXPIRED';

    public function handle(ScanExpiringPermitsAction $action): int
    {
        $this->info('Memulai pemindaian izin kerja K3 tambang...');

        $result = $action->execute();

        $this->line("Total izin aktif diperiksa : {$result['total_active_scanned']}");
        $this->line("Total izin kedaluwarsa     : {$result['expired_count']}");

        if ($result['expired_count'] > 0) {
            $this->warn('Izin yang diubah ke EXPIRED: '.implode(', ', $result['expired_permit_numbers']));
        } else {
            $this->info('Seluruh izin kerja lapangan masih dalam masa berlaku aman.');
        }

        return 0;
    }
}
