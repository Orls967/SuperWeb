<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\EvaluateCoverageGapAction;

class QualityCoverageGapCommand extends Command
{
    protected $signature = 'quality:coverage-gap {--target=80.0 : Target minimum line coverage}';

    protected $description = 'Mengevaluasi selisih gap cakupan test suite terhadap target';

    public function handle(EvaluateCoverageGapAction $action): int
    {
        $target = (float) $this->option('target');
        $res = $action->evaluate($target);

        $this->info(sprintf(
            'Target Coverage: %.1f%% | Aktual: %.1f%% | Gap: %.1f%% | Status: %s',
            $res['target_coverage'],
            $res['current_coverage'],
            $res['gap'],
            strtoupper($res['status'])
        ));

        return $res['status'] === 'pass' ? 0 : 1;
    }
}

