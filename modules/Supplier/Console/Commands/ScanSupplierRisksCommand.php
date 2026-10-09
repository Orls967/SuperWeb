<?php

declare(strict_types=1);

namespace Modules\Supplier\Console\Commands;

use Illuminate\Console\Command;
use Modules\Supplier\Application\Services\SupplierService;

class ScanSupplierRisksCommand extends Command
{
    protected $signature = 'sup:scan-risks';

    protected $description = 'Pindai risiko pemasok: konsentrasi, sertifikasi kedaluwarsa, sanksi, dan skor rendah';

    public function handle(SupplierService $service): int
    {
        $flags = $service->scanRisks();

        foreach ($flags as $flag) {
            $this->line("[{$flag['severity']}] {$flag['supplier']} — {$flag['type']}: {$flag['message']}");
        }

        $this->info('Pemindaian selesai: '.count($flags).' flag dibuka/dikonfirmasi.');

        return self::SUCCESS;
    }
}
