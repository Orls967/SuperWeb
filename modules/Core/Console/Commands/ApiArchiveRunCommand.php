<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\RetentionArchiveService;

class ApiArchiveRunCommand extends Command
{
    protected $signature = 'api:archive-run {--table= : Specific table name to archive} {--days=1825 : Retention threshold days}';

    protected $description = 'Archive historical data (> 5 years) to cold tables with SHA-256 chunk verification (W8-D52).';

    public function handle(RetentionArchiveService $service): int
    {
        $table = $this->option('table') ? (string) $this->option('table') : null;
        $days = (int) $this->option('days');

        $this->info('Starting historical cold archive execution...');

        $tables = $table ? [$table] : array_keys($service->getProviders());
        $archivedCount = 0;

        foreach ($tables as $t) {
            $this->line("Processing retention policy for [{$t}]...");
            $run = $service->archive($t, $days);
            if ($run !== null) {
                $this->info("Archived {$run->rows_archived} rows from [{$t}] to [{$run->archive_table}]. Chunk SHA-256: {$run->chunk_sha256}");
                $archivedCount++;
            } else {
                $this->line("No expired rows found for [{$t}].");
            }
        }

        $this->info("Completed archive execution across {$archivedCount} tables.");

        return self::SUCCESS;
    }
}

