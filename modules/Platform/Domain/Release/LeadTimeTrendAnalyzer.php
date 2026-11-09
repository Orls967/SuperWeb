<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class LeadTimeTrendAnalyzer
{
    /**
     * Determines whether lead time is improving (downward trend), stable, or degrading.
     *
     * @param  list<float>  $historicalLeadTimesHours  chronological
     * @return array{trend: string, percentage_change: float, average: float}
     */
    public function analyzeTrend(array $historicalLeadTimesHours): array
    {
        $count = count($historicalLeadTimesHours);
        if ($count < 2) {
            return ['trend' => 'INSUFFICIENT_DATA', 'percentage_change' => 0.0, 'average' => $historicalLeadTimesHours[0] ?? 0.0];
        }

        $half = (int) floor($count / 2);
        $firstHalf = array_slice($historicalLeadTimesHours, 0, $half);
        $secondHalf = array_slice($historicalLeadTimesHours, $half);

        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);

        $changePct = $avgFirst > 0.0 ? round((($avgSecond - $avgFirst) / $avgFirst) * 100.0, 2) : 0.0;

        $trend = 'STABLE';
        if ($changePct < -10.0) {
            $trend = 'IMPROVING'; // Lead time decreasing
        } elseif ($changePct > 10.0) {
            $trend = 'DEGRADING'; // Lead time increasing
        }

        return [
            'trend' => $trend,
            'percentage_change' => $changePct,
            'average' => round(array_sum($historicalLeadTimesHours) / $count, 2),
        ];
    }
}
