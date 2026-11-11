<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\ApiUsageRollupService;

class ApiUsageRollupCommand extends Command
{
    protected $signature = 'api:usage-rollup {--hour= : Specific bucket hour YYYY-MM-DD-HH}';

    protected $description = 'Idempotently aggregate raw API usage events into hourly rollups (W8-D47).';

    public function handle(ApiUsageRollupService $service): int
    {
        $hour = $this->option('hour') ? (string) $this->option('hour') : null;
        $this->info('Running API usage rollup'.($hour ? " for hour [{$hour}]" : '...'));

        $processed = $service->rollup($hour);

        $this->info("Completed API usage rollup. {$processed} hourly buckets updated.");

        return self::SUCCESS;
    }
}
