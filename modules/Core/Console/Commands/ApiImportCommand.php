<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\StreamingBulkImportService;

class ApiImportCommand extends Command
{
    protected $signature = 'api:import {target? : Target dataset key} {file? : CSV file path} {job? : Job ID to resume} {--job= : Job ID to resume} {--resume : Resume processing existing job} {--tenant=tenant-default : Tenant identifier}';

    protected $description = 'Ingest and process bulk CSV dataset in 500-row chunks using streaming I/O (W8-D50).';

    public function handle(StreamingBulkImportService $service): int
    {
        $jobId = $this->argument('job') ? (int) $this->argument('job') : ($this->option('job') ? (int) $this->option('job') : null);

        if ($jobId !== null || $this->option('resume')) {
            $job = \Modules\Core\Domain\Models\CoreBulkImportJob::findOrFail($jobId);
            $this->info("Resuming bulk import for Job #{$job->id} [{$job->target_key}]...");
            $completed = $service->executeJob($job);
            $this->info("Import completed successfully. {$completed->processed_rows} rows processed.");
            return self::SUCCESS;
        }

        $targetKey = (string) $this->argument('target');
        $filePath = (string) $this->argument('file');
        $tenantId = (string) $this->option('tenant');

        $this->info("Validating bulk import for target [{$targetKey}] from [{$filePath}]...");

        $job = $service->validateFile($tenantId, $targetKey, $filePath, basename($filePath));

        $this->line("Validation status: {$job->status}. Total rows: {$job->total_rows} (valid: {$job->valid_rows}, errors: {$job->error_rows}).");

        if ($job->status === 'awaiting_approval') {
            $this->warn("Import dataset exceeds 10,000 rows. Awaiting approval (Job #{$job->id}).");
            return self::SUCCESS;
        }

        if ($job->status === 'failed') {
            $this->error("Import validation failed with {$job->error_rows} errors.");
            return self::FAILURE;
        }

        $this->info("Executing chunked import execution (Job #{$job->id})...");
        $completed = $service->executeJob($job);

        $this->info("Import completed successfully. {$completed->processed_rows} rows processed.");

        return self::SUCCESS;
    }
}
