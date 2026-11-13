<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\RunWhatIfTwinScenarioAction;
use Modules\ControlTower\Domain\Models\TwinScenario;

final class ControlTowerWhatIfTwinService
{
    public function __construct(
        private readonly RunWhatIfTwinScenarioAction $runAction,
    ) {}

    public function simulatePortClosure14Days(int $activeOrderCount = 150): TwinScenario
    {
        return $this->runAction->execute(
            name: 'Simulasi Penutupan Pelabuhan 14 Hari',
            shockType: 'PORT_CLOSURE',
            shockMagnitudeBps: 2500,
            durationDays: 14,
            baselineOtifBps: 9600,
            activeOrderCount: $activeOrderCount,
            dailyGrossMarginIdr: 30_000_000,
        );
    }

    public function simulateRawMaterialInflation20Pct(): TwinScenario
    {
        return $this->runAction->execute(
            name: 'Simulasi Kenaikan Harga Bahan Baku 20%',
            shockType: 'MATERIAL_INFLATION',
            shockMagnitudeBps: 2000,
            durationDays: 30,
            baselineOtifBps: 9600,
            activeOrderCount: 200,
            dailyGrossMarginIdr: 35_000_000,
        );
    }
}
