<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Manufacturing\Application\Services\PlanningService;
use Modules\Manufacturing\Domain\Models\MrpRun;

/**
 * 36.8 MRP idempoten: run_key = hash(snapshot input + parameter).
 * Input sama → run completed lama dipakai ulang, tidak dihitung ulang.
 */
class RunMrpCommand extends Command
{
    protected $signature = 'mfg:run-mrp
        {--horizon=56 : Horizon perencanaan dalam hari}
        {--bucket=7 : Lebar bucket periode dalam hari}
        {--scenario : Mode what-if (tanpa menulis planned order nyata)}';

    protected $description = 'Jalankan MRP (ledakan BOM, netting, lot sizing) + CRP — idempoten per snapshot input';

    public function handle(PlanningService $planning): int
    {
        $horizon = max(7, (int) $this->option('horizon'));
        $bucket = max(1, (int) $this->option('bucket'));
        $scenario = (bool) $this->option('scenario');

        try {
            $run = $planning->runMrp($horizon, $bucket, $scenario);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = $run->summary ?? [];
        $this->info(sprintf(
            'Run %s selesai (key %s…): %s bucket, %d requirement, %d planned produksi, %d planned beli, bottleneck %d.',
            $run->id,
            substr($run->run_key, 0, 12),
            $summary['buckets'] ?? 0,
            $summary['requirements'] ?? 0,
            $summary['planned_production'] ?? 0,
            $summary['planned_purchase'] ?? 0,
            $summary['bottlenecks'] ?? 0,
        ));

        $previous = MrpRun::where('id', '!=', $run->id)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        if ($previous !== null) {
            $this->line(sprintf(
                'Perbandingan vs run %s: requirement %d → %d, bottleneck %d → %d.',
                substr($previous->id, 0, 8),
                (int) ($previous->summary['requirements'] ?? 0),
                (int) ($summary['requirements'] ?? 0),
                (int) ($previous->summary['bottlenecks'] ?? 0),
                (int) ($summary['bottlenecks'] ?? 0),
            ));
        }

        return self::SUCCESS;
    }
}
