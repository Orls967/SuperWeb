<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\AsyncZipExportService;

class ApiExportPurgeCommand extends Command
{
    protected $signature = 'api:export-purge';

    protected $description = 'Purge expired bulk export ZIP archives (> 7 days) from storage (W8-D50).';

    public function handle(AsyncZipExportService $service): int
    {
        $this->info('Purging expired export files...');

        $purgedCount = $service->purgeExpired();

        $this->info("Purged {$purgedCount} expired export archives.");

        return self::SUCCESS;
    }
}

