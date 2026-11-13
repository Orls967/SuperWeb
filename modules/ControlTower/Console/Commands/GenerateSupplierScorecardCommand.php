<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Services\SupplierScorecardOrchestratorService;

final class GenerateSupplierScorecardCommand extends Command
{
    protected $signature = 'tower:supplier-scorecard {--supplier=} {--period=}';

    protected $description = 'Generate and persist multi-criteria supplier performance scorecards';

    public function handle(SupplierScorecardOrchestratorService $orchestrator): int
    {
        $supplier = $this->option('supplier');
        $period = $this->option('period');

        $snapshots = $orchestrator->generateForPeriod(
            period: is_string($period) && $period !== '' ? $period : null,
            supplierRef: is_string($supplier) && $supplier !== '' ? $supplier : null,
        );

        $this->info(sprintf('Generated %d supplier scorecard snapshot(s).', $snapshots->count()));

        foreach ($snapshots as $snapshot) {
            $this->line(sprintf(
                ' - %s (%s) [%s]: score=%d, tier=%s',
                $snapshot->supplier_ref,
                $snapshot->supplier_name,
                $snapshot->period,
                $snapshot->composite_score,
                $snapshot->tier,
            ));
        }

        return self::SUCCESS;
    }
}
