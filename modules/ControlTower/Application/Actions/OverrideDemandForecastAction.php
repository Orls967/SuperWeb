<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\ForecastOverride;

final class OverrideDemandForecastAction
{
    public function execute(
        DemandForecast $forecast,
        int $newQty,
        string $reason,
        ?int $actorUserId = null,
    ): ForecastOverride {
        if ($newQty < 0) {
            throw new InvalidArgumentException('NEGATIVE_FORECAST_QTY: Kuantitas override tidak boleh negatif.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('OVERRIDE_REASON_REQUIRED: Alasan override wajib diisi.');
        }

        return DB::transaction(function () use ($forecast, $newQty, $reason, $actorUserId): ForecastOverride {
            $previous = (int) $forecast->forecast_qty;
            $forecast->update([
                'forecast_qty' => $newQty,
                'is_overridden' => true,
                'override_reason' => trim($reason),
            ]);

            return ForecastOverride::create([
                'demand_forecast_id' => $forecast->id,
                'previous_qty' => $previous,
                'new_qty' => $newQty,
                'reason' => trim($reason),
                'overridden_by_user_id' => $actorUserId,
            ]);
        });
    }
}
