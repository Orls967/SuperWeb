<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\SweepInTransitAction;

class TransitSweepCommand extends Command
{
    protected $signature = 'wms:transit-sweep {--max-hours=72 : Batas jam SLA in-transit}';

    protected $description = 'Menyapu pengiriman in-transit yang melewati batas SLA';

    public function handle(SweepInTransitAction $action): int
    {
        $maxHours = (int) $this->option('max-hours');
        $res = $action->execute($maxHours);
        $this->info('Pengiriman in-transit overdue (>'.$maxHours.'j): '.$res['overdue_count']);

        return self::SUCCESS;
    }
}
