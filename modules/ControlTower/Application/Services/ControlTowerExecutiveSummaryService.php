<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\TwinScenario;

final class ControlTowerExecutiveSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function buildBrief(): array
    {
        $latestKpi = ControlTowerKpiSnapshot::query()->latest('id')->first();
        $publishedSop = SopCycle::query()->where('status', 'published')->latest('id')->first();
        $latestTwin = TwinScenario::query()->latest('id')->first();

        return [
            'otif_bps' => (int) ($latestKpi?->otif_bps ?? 0),
            'cash_to_cash_days' => (int) ($latestKpi?->cash_to_cash_days ?? 0),
            'active_sop_cycle' => $publishedSop?->cycle_code,
            'latest_twin_scenario' => $latestTwin?->scenario_code,
        ];
    }
}

