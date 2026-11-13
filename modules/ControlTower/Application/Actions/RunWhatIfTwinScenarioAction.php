<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Modules\ControlTower\Domain\Algorithms\WhatIfTwinSimulator;
use Modules\ControlTower\Domain\Models\TwinScenario;

final class RunWhatIfTwinScenarioAction
{
    public function __construct(
        private readonly WhatIfTwinSimulator $simulator,
    ) {}

    public function execute(
        string $name,
        string $shockType,
        int $shockMagnitudeBps,
        int $durationDays,
        int $baselineOtifBps = 9600,
        int $activeOrderCount = 100,
        int $dailyGrossMarginIdr = 25_000_000,
        ?int $actorUserId = null,
    ): TwinScenario {
        $sim = $this->simulator->simulate(
            shockType: $shockType,
            shockMagnitudeBps: $shockMagnitudeBps,
            durationDays: $durationDays,
            baselineOtifBps: $baselineOtifBps,
            activeOrderCount: $activeOrderCount,
            dailyGrossMarginIdr: $dailyGrossMarginIdr,
        );

        $code = 'TWN-'.strtoupper(substr(hash('sha256', $name.'|'.$shockType.'|'.(string) TwinScenario::count()), 0, 8));

        return TwinScenario::create([
            'scenario_code' => $code,
            'name' => $name,
            'shock_type' => $sim['shock_type'],
            'shock_magnitude_bps' => $shockMagnitudeBps,
            'duration_days' => $durationDays,
            'baseline_otif_bps' => $sim['baseline_otif_bps'],
            'simulated_otif_bps' => $sim['simulated_otif_bps'],
            'at_risk_orders_count' => $sim['at_risk_orders_count'],
            'estimated_margin_delta_idr' => $sim['estimated_margin_delta_idr'],
            'status' => 'completed',
            'created_by_user_id' => $actorUserId,
        ]);
    }
}
