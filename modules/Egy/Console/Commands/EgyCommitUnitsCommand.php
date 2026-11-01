<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\GenerationAsset;

class EgyCommitUnitsCommand extends Command
{
    protected $signature = 'egy:commit-units';

    protected $description = 'Plans daily unit commitment and marginal merit order dispatch for generation assets';

    public function handle(EnergyGridAndMeteringService $gridService): int
    {
        $assets = GenerationAsset::where('status', 'ONLINE')->get();
        $this->info("Planning unit commitment for {$assets->count()} online generation assets...");

        if ($assets->isEmpty()) {
            $this->info('No online generation assets found. Using default reserve units.');
            $units = [
                ['id' => 'GEN-DEFAULT-01', 'min_power' => 10, 'max_power' => 100, 'cost_per_mwh' => 20, 'startup_cost' => 100],
            ];
        } else {
            $units = $assets->map(fn (GenerationAsset $a) => [
                'id' => $a->asset_code,
                'min_power' => (int) round($a->installed_capacity_mw * 0.2),
                'max_power' => (int) round($a->installed_capacity_mw),
                'cost_per_mwh' => (int) ($a->marginal_cost_per_mwh_minor ?: 15),
                'startup_cost' => 50,
            ])->all();
        }

        $demands = [30, 45, 60];
        $plan = $gridService->planUnitCommitment($units, $demands);

        $this->info("Unit Commitment Status: {$plan['status']}, Total Cost: {$plan['total_cost']}");

        return Command::SUCCESS;
    }
}
