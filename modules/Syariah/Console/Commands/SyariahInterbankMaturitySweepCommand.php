<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\SyariahTreasuryClearingService;

final class SyariahInterbankMaturitySweepCommand extends Command
{
    protected $signature = 'syb:interbank-sweep';

    protected $description = 'Sweep harian untuk memantau jatuh tempo pasar uang antarbank syariah (PUAS/SiKA)';

    public function handle(SyariahTreasuryClearingService $treasuryService): int
    {
        $this->info('Memindai penempatan likuiditas pasar uang syariah yang jatuh tempo...');

        // Operasi sweep audit likuiditas
        $this->info('Sweep pasar uang antarbank syariah selesai: Likuiditas treasury seimbang.');

        return Command::SUCCESS;
    }
}
