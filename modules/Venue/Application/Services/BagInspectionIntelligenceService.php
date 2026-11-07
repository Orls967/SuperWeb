<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\VenueBagInspection;

final class BagInspectionIntelligenceService
{
    public const DEFAULT_INSPECTION_RATIO_BPS = 1000; // 10.00% audit acak

    /**
     * Determine if an incoming attendee should be flagged for random bag audit.
     */
    public function shouldInspectRandomly(int $inspectionRatioBps = self::DEFAULT_INSPECTION_RATIO_BPS): bool
    {
        $roll = random_int(1, 10000);
        return $roll <= $inspectionRatioBps;
    }

    /**
     * Aggregate security inspection metrics and contraband confiscation trends.
     *
     * @return array{
     *     total_inspections: int,
     *     passed_inspections: int,
     *     flagged_contraband: int,
     *     contraband_rate_percent: float,
     *     confiscated_categories: array<string, int>,
     *     compliance_status: string
     * }
     */
    public function evaluateInspectionMetrics(int $eventId): array
    {
        $inspections = VenueBagInspection::where('event_id', $eventId)->get();
        $total = $inspections->count();
        $passed = $inspections->where('status', 'passed')->count();
        $confiscated = $inspections->where('contraband_found', true)->count();

        $categories = [];
        foreach ($inspections as $ins) {
            if ($ins->contraband_found && $ins->confiscated_items !== null) {
                $items = is_array($ins->confiscated_items) ? $ins->confiscated_items : [$ins->confiscated_items];
                foreach ($items as $item) {
                    $name = (string) $item;
                    $categories[$name] = ($categories[$name] ?? 0) + 1;
                }
            }
        }

        $rate = $total > 0 ? round(($confiscated / $total) * 100, 2) : 0.0;
        $status = 'COMPLIANT';
        if ($rate > 5.0) {
            $status = 'HIGH_ALERT_CONTRABAND_SPIKE';
        }

        return [
            'total_inspections' => $total,
            'passed_inspections' => $passed,
            'flagged_contraband' => $confiscated,
            'contraband_rate_percent' => $rate,
            'confiscated_categories' => $categories,
            'compliance_status' => $status,
        ];
    }
}
