<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Services\BackfillChunkProcessor;

class BackfillRunCommand extends Command
{
    protected $signature = 'platform:backfill-run
                            {job_name : Nama pekerjaan backfill}
                            {--max_chunks=5 : Batas chunk yang dieksekusi per run}';

    protected $description = 'Menjalankan eksekusi data backfill secara berulang dan idempoten per chunk';

    public function handle(BackfillChunkProcessor $processor): int
    {
        $jobName = $this->argument('job_name');
        $maxChunks = (int) $this->option('max_chunks');

        $backfill = DataBackfill::where('job_name', $jobName)->first();
        if ($backfill === null) {
            $this->error("Pekerjaan backfill '{$jobName}' tidak ditemukan.");

            return 1;
        }

        if ($backfill->status === 'completed') {
            $this->info("Pekerjaan backfill '{$jobName}' sudah berstatus completed.");

            return 0;
        }

        $chunksRun = 0;
        while ($backfill->cursor_pointer < $backfill->total_records && $chunksRun < $maxChunks) {
            $chunksRun++;
            $processed = $processor->processNextChunk($backfill, function (array $records): int {
                return count($records);
            });

            $this->line("Chunk diproses: {$processed} record. Pointer saat ini: {$backfill->fresh()->cursor_pointer}/{$backfill->total_records}");
        }

        $this->info("Sesi eksekusi selesai. Status backfill: {$backfill->fresh()->status}");

        return 0;
    }
}
