<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class SupplierLeadTimeVariabilityAnalyzer
{
    /**
     * @param  list<int>  $actualLeadTimeDays
     * @return array{avg_days: int, max_days: int, reliability_bps: int}
     */
    public function analyze(array $actualLeadTimeDays, int $promisedDays): array
    {
        if ($actualLeadTimeDays === []) {
            return ['avg_days' => $promisedDays, 'max_days' => $promisedDays, 'reliability_bps' => 10_000];
        }

        $onTime = 0;
        $sum = 0;
        $max = 0;
        foreach ($actualLeadTimeDays as $days) {
            $sum += $days;
            if ($days > $max) {
                $max = $days;
            }
            if ($days <= $promisedDays) {
                $onTime++;
            }
        }

        $count = count($actualLeadTimeDays);

        return [
            'avg_days' => intdiv($sum, $count),
            'max_days' => $max,
            'reliability_bps' => intdiv($onTime * 10_000, $count),
        ];
    }
}
