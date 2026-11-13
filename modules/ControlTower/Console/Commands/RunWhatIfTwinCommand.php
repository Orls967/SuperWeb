<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\ControlTower\Application\Services\TwinWhatIfOrchestratorService;

final class RunWhatIfTwinCommand extends Command
{
    protected $signature = 'tower:whatif {scenario=PORT_CLOSURE} {--magnitude=2500} {--days=14}';

    protected $description = 'Run zero-mutation Supply Chain Digital Twin What-If scenario in sandbox mode';

    public function handle(TwinWhatIfOrchestratorService $orchestrator): int
    {
        try {
            $result = $orchestrator->runScenario(
                shockType: (string) $this->argument('scenario'),
                shockMagnitudeBps: (int) $this->option('magnitude'),
                durationDays: (int) $this->option('days'),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'What-If %s (%s): OTIF %d -> %d bps | at_risk=%d | zero_mutation=%s | hash=%s',
            $result['scenario_code'],
            $result['shock_type'],
            $result['baseline_otif_bps'],
            $result['simulated_otif_bps'],
            $result['at_risk_orders_count'],
            $result['zero_mutation_verified'] ? 'true' : 'false',
            $result['after_hash'],
        ));

        return self::SUCCESS;
    }
}
