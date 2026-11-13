<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Simulation;

use Carbon\CarbonInterface;
use Modules\ControlTower\Domain\Algorithms\WhatIfTwinSimulator;
use Modules\Core\Contracts\SimTickHandlerInterface;

final class ControlTowerTwinTickHandler implements SimTickHandlerInterface
{
    public function __construct(
        private readonly WhatIfTwinSimulator $simulator,
    ) {}

    public function name(): string
    {
        return 'control_tower_twin';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return $dayNumber >= 1;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $sim = $this->simulator->simulate(
            shockType: 'PORT_CLOSURE',
            shockMagnitudeBps: 1500,
            durationDays: min(14, max(1, $dayNumber)),
            baselineOtifBps: 9600,
            activeOrderCount: 120,
            dailyGrossMarginIdr: 25_000_000,
        );

        return [
            'tick_date' => $virtualNow->toDateString(),
            'day' => $dayNumber,
            'at_risk_orders' => $sim['at_risk_orders_count'],
            'simulated_otif_bps' => $sim['simulated_otif_bps'],
            'margin_delta_idr' => $sim['estimated_margin_delta_idr'],
        ];
    }
}
