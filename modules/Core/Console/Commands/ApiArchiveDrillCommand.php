<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\RetentionArchiveService;
use Modules\Core\Domain\Models\CoreRetentionRun;

class ApiArchiveDrillCommand extends Command
{
    protected $signature = 'api:archive-drill {--run= : Specific retention run ID}';

    protected $description = 'Execute cold archive restoration audit drill to verify chunk SHA-256 parity (W8-D52).';

    public function handle(RetentionArchiveService $service): int
    {
        $runId = $this->option('run') ? (int) $this->option('run') : null;
        $this->info('Starting disaster-recovery cold archive drill...');

        $query = CoreRetentionRun::query();
        if ($runId) {
            $query->where('id', $runId);
        } else {
            $query->orderByDesc('id')->limit(5);
        }

        $runs = $query->get();
        if ($runs->isEmpty()) {
            $this->warn('No retention runs available for drill restore.');

            return self::SUCCESS;
        }

        $mismatches = 0;
        foreach ($runs as $run) {
            $drill = $service->drillRestore($run);
            if ($drill->matched) {
                $this->info("Drill PASSED for run #{$run->id} [{$run->source_table}]: Restored {$drill->restored_rows} rows, SHA-256 matched.");
            } else {
                $this->error("Drill FAILED for run #{$run->id} [{$run->source_table}]: Expected {$drill->expected_sha256}, got {$drill->actual_sha256}.");
                $mismatches++;
            }
        }

        return $mismatches === 0 ? self::SUCCESS : self::FAILURE;
    }
}
