<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Application\Actions;

use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;
use Modules\Shared\Application\BaseAction;

class ScoreDriverBehaviorAndSafetyIndexAction extends BaseAction
{
    /**
     * Compute driver behavior metrics and safety index (0-100) based on recent ticks.
     *
     * @return array{vehicle_id: int, safety_score: int, risk_tier: string, overspeed_events: int, harsh_events: int, tick_count: int}
     */
    public function handle(int $vehicleId, int $lookbackDays = 7): array
    {
        $since = now()->subDays(max(1, $lookbackDays));

        $ticks = TelematicsTick::where('vehicle_id', $vehicleId)
            ->where('recorded_at', '>=', $since)
            ->get();

        if ($ticks->isEmpty()) {
            return [
                'vehicle_id' => $vehicleId,
                'safety_score' => 100,
                'risk_tier' => 'LOW',
                'overspeed_events' => 0,
                'harsh_events' => 0,
                'tick_count' => 0,
            ];
        }

        $overspeedEvents = 0;
        $harshEvents = 0;

        foreach ($ticks as $tick) {
            // Speed over 100 km/h is flagged
            if ($tick->speed_kmh > 100.0) {
                $overspeedEvents++;
            }
            // RPM exceeding 4500 indicates harsh acceleration/revving
            if ($tick->rpm > 4500) {
                $harshEvents++;
            }
        }

        // Deduct points: -5 per overspeed, -3 per harsh event, capped at 100 deduction
        $deductions = ($overspeedEvents * 5) + ($harshEvents * 3);
        $safetyScore = max(0, min(100, 100 - $deductions));

        $riskTier = match (true) {
            $safetyScore >= 85 => 'LOW',
            $safetyScore >= 70 => 'MODERATE',
            $safetyScore >= 50 => 'HIGH',
            default => 'CRITICAL',
        };

        return [
            'vehicle_id' => $vehicleId,
            'safety_score' => $safetyScore,
            'risk_tier' => $riskTier,
            'overspeed_events' => $overspeedEvents,
            'harsh_events' => $harshEvents,
            'tick_count' => $ticks->count(),
        ];
    }
}
