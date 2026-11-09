<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\SloTier;
use Modules\Platform\Domain\Models\ServiceSlo;

class TrackServiceSloAction
{
    public function registerOrUpdate(
        string $serviceName,
        SloTier $tier = SloTier::TIER_2_HIGH,
        ?int $targetBps = null
    ): ServiceSlo {
        $bps = $targetBps ?? $tier->defaultTargetBps();
        $monthlyMins = 30 * 24 * 60;
        $budgetMins = (int) round($monthlyMins * (1 - ($bps / 10000.0)));

        return ServiceSlo::updateOrCreate(
            ['service_name' => $serviceName],
            [
                'tier' => $tier->value,
                'target_sli_bps' => $bps,
                'error_budget_mins' => $budgetMins,
                'status' => 'healthy',
            ]
        );
    }
}
