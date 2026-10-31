<?php

declare(strict_types=1);

namespace Modules\Telematics\Application\Actions;

use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Algorithms\Stats\RobustStatistics;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsTick;

class RecomputeRobustBaselineAction
{
    private const DEFAULT_K_BPS = 35000; // 3.5

    private const ABSOLUTE_MINIMUM_THRESHOLDS = [
        'coolant_temp_deci_c' => 150, // 15.0 C delta
        'battery_mv' => 1500,         // 1.5 V delta
        'fuel_permil' => 150,         // 15% delta
    ];

    public function execute(int $vehicleId, string $metric, int $windowDays = 7): TelematicsBaseline
    {
        $since = now()->subDays($windowDays);

        $ticks = TelematicsTick::where('vehicle_id', $vehicleId)
            ->where('recorded_at', '>=', $since)
            ->whereNotNull($metric)
            ->orderBy('recorded_at')
            ->get();

        $values = $ticks->pluck($metric)->map(fn ($v) => (int) $v)->all();

        if (empty($values)) {
            return TelematicsBaseline::updateOrCreate(
                ['vehicle_id' => $vehicleId, 'metric' => $metric],
                [
                    'median_value' => 0,
                    'mad_value' => 0,
                    'sample_count' => 0,
                    'calculated_at' => now(),
                    'window_days' => $windowDays,
                ]
            );
        }

        $stats = new RobustStatistics;
        $medianRat = $stats->median($values);
        $madRat = $stats->mad($values, $medianRat);

        $median = $medianRat->toScale(0, RoundingMode::HalfUp)->toInt();
        $mad = $madRat->toScale(0, RoundingMode::HalfUp)->toInt();

        return TelematicsBaseline::updateOrCreate(
            ['vehicle_id' => $vehicleId, 'metric' => $metric],
            [
                'median_value' => $median,
                'mad_value' => $mad,
                'sample_count' => count($values),
                'calculated_at' => now(),
                'window_days' => $windowDays,
            ]
        );
    }

    public function isAnomaly(string $metric, int $value, TelematicsBaseline $baseline): bool
    {
        $median = $baseline->median_value;
        $mad = $baseline->mad_value;
        $diff = abs($value - $median);

        if ($mad === 0) {
            $absMin = self::ABSOLUTE_MINIMUM_THRESHOLDS[$metric] ?? 100;

            return $diff > $absMin;
        }

        // O-2: |x - median| * 10_000 * 10_000 > k_bps * 14_826 * MAD
        $lhs = $diff * 100_000_000;
        $rhs = self::DEFAULT_K_BPS * 14826 * $mad;

        return $lhs > $rhs;
    }
}
