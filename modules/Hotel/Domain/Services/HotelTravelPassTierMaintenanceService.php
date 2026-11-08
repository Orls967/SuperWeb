<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Algorithms\GlobalTierQualifier;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTravelPassTierMaintenanceService
{
    public function __construct(
        protected ?GlobalTierQualifier $qualifier = null
    ) {
        $this->qualifier = $qualifier ?? new GlobalTierQualifier;
    }

    /**
     * @return array{upgraded_count: int, downgraded_count: int, retained_count: int}
     */
    public function evaluateAllActivePasses(): array
    {
        $passes = HtlTravelPass::where('is_active', true)->get();
        $upgraded = 0;
        $downgraded = 0;
        $retained = 0;

        foreach ($passes as $pass) {
            $cert = $this->qualifier->evaluate(
                rolling365Nights: $pass->cumulative_nights,
                rolling365SpendIdr: $pass->cumulative_spend_idr,
                currentTier: $pass->tier
            );

            if ($cert->isUpgraded) {
                $pass->update(['tier' => $cert->qualifiedTier]);
                $upgraded++;
            } elseif ($cert->isDowngraded) {
                $pass->update(['tier' => $cert->qualifiedTier]);
                $downgraded++;
            } else {
                $retained++;
            }
        }

        return [
            'upgraded_count' => $upgraded,
            'downgraded_count' => $downgraded,
            'retained_count' => $retained,
        ];
    }
}
