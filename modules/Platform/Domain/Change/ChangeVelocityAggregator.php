<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeVelocityAggregator
{
    /**
     * Aggregates completed change throughput per module over time.
     *
     * @param  list<array{module: string, closed_at: string, duration_hours: float}>  $closedChanges
     * @return array<string, array{total_closed: int, average_lead_time_hours: float, throughput_share_pct: float}>
     */
    public function aggregateVelocity(array $closedChanges): array
    {
        $grouped = [];
        $totalAll = count($closedChanges);

        foreach ($closedChanges as $c) {
            $mod = $c['module'] ?? 'Unknown';
            if (! isset($grouped[$mod])) {
                $grouped[$mod] = ['count' => 0, 'total_hours' => 0.0];
            }
            $grouped[$mod]['count']++;
            $grouped[$mod]['total_hours'] += ($c['duration_hours'] ?? 0.0);
        }

        $result = [];
        foreach ($grouped as $mod => $stats) {
            $count = $stats['count'];
            $avgHours = $count > 0 ? round($stats['total_hours'] / $count, 2) : 0.0;
            $share = $totalAll > 0 ? round(($count / $totalAll) * 100.0, 1) : 0.0;

            $result[$mod] = [
                'total_closed' => $count,
                'average_lead_time_hours' => $avgHours,
                'throughput_share_pct' => $share,
            ];
        }

        return $result;
    }
}
