<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\TwinScenario;

final class ControlTowerDashboardViewModel
{
    /**
     * @return Collection<int, SopCycle>
     */
    public function sopCycles(): Collection
    {
        return SopCycle::query()->latest('id')->limit(25)->get();
    }

    /**
     * @return Collection<int, DemandForecast>
     */
    public function forecasts(): Collection
    {
        return DemandForecast::query()->latest('id')->limit(30)->get();
    }

    /**
     * @return Collection<int, TwinScenario>
     */
    public function twinScenarios(): Collection
    {
        return TwinScenario::query()->latest('id')->limit(25)->get();
    }

    /**
     * @return Collection<int, ControlTowerKpiSnapshot>
     */
    public function kpiSnapshots(): Collection
    {
        return ControlTowerKpiSnapshot::query()->latest('id')->limit(20)->get();
    }

    /**
     * @return Collection<int, SafetyStockPolicy>
     */
    public function safetyStockPolicies(): Collection
    {
        return SafetyStockPolicy::query()->orderBy('abc_class')->orderBy('item_code')->limit(50)->get();
    }

    /**
     * @return array<string, int>
     */
    public function summaryMetrics(): array
    {
        return [
            'echelon_count' => EchelonStock::query()->count(),
            'active_alerts' => DisruptionAlert::query()->where('status', 'active')->count(),
            'sop_cycles' => SopCycle::query()->count(),
            'twin_scenarios' => TwinScenario::query()->count(),
        ];
    }
}
