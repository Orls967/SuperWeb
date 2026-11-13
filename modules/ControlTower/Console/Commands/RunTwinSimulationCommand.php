<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Actions\RunWhatIfTwinScenarioAction;

final class RunTwinSimulationCommand extends Command
{
    protected $signature = 'tower:twin-simulate {--shock=PORT_CLOSURE} {--magnitude=2000} {--days=14}';

    protected $description = 'Run deterministic Control Tower What-If Digital Twin simulation';

    public function handle(RunWhatIfTwinScenarioAction $action): int
    {
        $scenario = $action->execute(
            name: 'CLI Twin Run '.(string) $this->option('shock'),
            shockType: (string) $this->option('shock'),
            shockMagnitudeBps: (int) $this->option('magnitude'),
            durationDays: (int) $this->option('days'),
        );

        $this->info("Twin scenario {$scenario->scenario_code}: OTIF {$scenario->baseline_otif_bps} -> {$scenario->simulated_otif_bps} bps.");

        return self::SUCCESS;
    }
}
