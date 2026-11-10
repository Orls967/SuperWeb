<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\ScanAggregateGapsAction;

class SpineGapScanCommand extends Command
{
    protected $signature = 'spine:gap-scan
        {--type= : Filter aggregate type}
        {--id= : Filter aggregate id}';

    protected $description = 'Scan outbox stream for aggregate sequence gaps and record them idempotently (every 15 mins)';

    public function handle(ScanAggregateGapsAction $action): int
    {
        $aggType = $this->option('type') ? (string) $this->option('type') : null;
        $aggId = $this->option('id') ? (string) $this->option('id') : null;

        $this->info('Starting aggregate sequence gap scan...');
        $result = $action->execute($aggType, $aggId);

        $this->info("Gap scan completed. New gaps found: {$result['gaps_found']}, resolved: {$result['gaps_resolved']}.");

        return self::SUCCESS;
    }
}
