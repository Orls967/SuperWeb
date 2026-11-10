<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\SimCheckpoint;

class SimCheckpointPruneCommand extends Command
{
    protected $signature = 'sim:checkpoint-prune
        {--retention-days=30 : Batas usia hari checkpoint yang dipertahankan}
        {--keep-interval=50 : Interval hari milestone yang selalu disimpan}';

    protected $description = 'Memangkas checkpoint simulasi lama yang tidak berada pada kelipatan interval retensi';

    public function handle(): int
    {
        $retentionDays = max(1, (int) $this->option('retention-days'));
        $keepInterval = max(1, (int) $this->option('keep-interval'));

        $cutoffDate = now()->subDays($retentionDays);

        $prunedCount = SimCheckpoint::query()
            ->where('created_at', '<', $cutoffDate)
            ->whereRaw("day_number % {$keepInterval} != 0")
            ->delete();

        $this->info("Pembersihan checkpoint selesai. {$prunedCount} checkpoint non-milestone berhasil dipangkas.");

        return self::SUCCESS;
    }
}
