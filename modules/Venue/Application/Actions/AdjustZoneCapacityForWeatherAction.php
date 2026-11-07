<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\EntertainmentZone;

final class AdjustZoneCapacityForWeatherAction
{
    /**
     * @return array{zone: EntertainmentZone, action_taken: string}
     */
    public function execute(
        EntertainmentZone $zone,
        int $rainProbabilityBps,
        int $windSpeedKnots,
        string $managerNotes = ''
    ): array {
        return DB::transaction(function () use ($zone, $rainProbabilityBps, $windSpeedKnots) {
            $locked = EntertainmentZone::lockForUpdate()->findOrFail($zone->id);

            // Jika hujan lebat >= 8.000 bps atau angin >= 35 knot dan zona outdoor
            if (($rainProbabilityBps >= 8000 || $windSpeedKnots >= 35) && $locked->is_outdoor) {
                $locked->crowd_lockdown_active = true;
                $locked->save();

                return [
                    'zone' => $locked,
                    'action_taken' => 'OUTDOOR_LOCKDOWN_ACTIVATED',
                ];
            }

            // Normalisasi kembali jika cuaca membaik
            if ($rainProbabilityBps < 4000 && $windSpeedKnots < 20 && $locked->crowd_lockdown_active) {
                $locked->crowd_lockdown_active = false;
                $locked->save();

                return [
                    'zone' => $locked,
                    'action_taken' => 'LOCKDOWN_RELEASED',
                ];
            }

            return [
                'zone' => $locked,
                'action_taken' => 'MONITORED_NORMAL',
            ];
        });
    }
}
