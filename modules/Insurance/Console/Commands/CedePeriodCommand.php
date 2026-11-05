<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Actions\CedePeriodAction;

final class CedePeriodCommand extends Command
{
    protected $signature = 'ins:cede-period {period? : Periode dalam format YYYY-MM}';

    protected $description = 'Proses evaluasi stop loss dan penutupan cession berkala (Fase 157)';

    public function handle(CedePeriodAction $action, ClockInterface $clock): int
    {
        $periodYm = (string) ($this->argument('period') ?? CarbonImmutable::instance($clock->now())->format('Y-m'));

        $this->info("Menjalankan evaluasi cession periodik untuk periode {$periodYm}...");

        $events = $action->execute($periodYm);

        $this->info(sprintf('Cession periodik selesai. %d event cession stop loss tercatat.', count($events)));

        return self::SUCCESS;
    }
}

