<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Application\Services\NocCorrelationService;

class CorrelateTelecomAlarmsCommand extends Command
{
    protected $signature = 'tlx:correlate-alarms';

    protected $description = 'Korelasi alarm topologi jaringan dan supresi gangguan hilir Lini 14';

    public function handle(NocCorrelationService $nocService): int
    {
        $this->info('Memulai korelasi alarm topologi NOC...');

        $tickets = $nocService->correlateActiveAlarms();
        $this->info("Korelasi selesai. Tiket akar aktif: {$tickets->count()}.");

        return self::SUCCESS;
    }
}
