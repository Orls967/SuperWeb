<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningPlanShift;
use Modules\Mining\Domain\Services\MiningExternalFactors;

/**
 * Aksi Penyesuaian Ulang Target Shift akibat Cuaca Ekstrem / Hujan (D93-6, T-5).
 */
class RePlanShiftTargetsAction
{
    public function __construct(
        private readonly MiningExternalFactors $externalFactors
    ) {}

    /**
     * @param  array{
     *     mine_plan_id: string,
     *     from_date?: string,
     *     additional_rain_hours?: int
     * }  $params
     * @return array{shifts_updated: int, total_lost_hours: int, plan_status: string}
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $plan = MiningPlan::with('site')->findOrFail($params['mine_plan_id']);
            $fromDate = $params['from_date'] ?? CarbonImmutable::now()->toDateString();

            $shifts = MiningPlanShift::where('mine_plan_id', $plan->id)
                ->where('shift_date', '>=', $fromDate)
                ->get();

            $updatedCount = 0;
            $lostHours = 0;

            foreach ($shifts as $shift) {
                $weather = $this->externalFactors->getRainfallDowntime(
                    $plan->site->site_code,
                    CarbonImmutable::parse($shift->shift_date)
                );

                $rainDowntime = $params['additional_rain_hours'] ?? $weather['downtime_hours'];
                $newAdjusted = max(0, $shift->effective_hours_nominal - $rainDowntime - $shift->maintenance_downtime_hours);

                $shift->update([
                    'rain_downtime_hours' => $rainDowntime,
                    'effective_hours_adjusted' => $newAdjusted,
                ]);

                $lostHours += $rainDowntime;
                $updatedCount++;
            }

            if ($lostHours > 20) {
                $plan->update(['status' => 'revised']);
            }

            return [
                'shifts_updated' => $updatedCount,
                'total_lost_hours' => $lostHours,
                'plan_status' => (string) $plan->status->value,
            ];
        });
    }
}
