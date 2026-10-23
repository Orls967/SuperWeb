<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Modules\Shared\Application\BaseAction;

class UtilitySubmeterAnomalyAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $meterNumber = (string) $params['meter_number'];
        $utilityType = (string) ($params['utility_type'] ?? 'electricity'); // electricity, water, gas
        $currentReading = (int) $params['current_reading'];
        $previousReading = (int) $params['previous_reading'];
        $historicalAverageConsumption = (int) $params['historical_average_consumption'];

        $currentConsumption = $currentReading - $previousReading;

        if ($currentConsumption < 0) {
            // Negative consumption indicates meter reset, rollback, or malfunction
            $severity = 'critical';
            $isAnomaly = true;
            $anomalyType = 'negative_meter_rollback';
            $deviationPct = -100.0;
        } elseif ($historicalAverageConsumption <= 0) {
            $severity = 'normal';
            $isAnomaly = false;
            $anomalyType = 'none_baseline_absent';
            $deviationPct = 0.0;
        } else {
            $deviationPct = round((($currentConsumption - $historicalAverageConsumption) / $historicalAverageConsumption) * 100, 2);

            if ($deviationPct >= 100.0) {
                // Spike of +100% or more (2x baseline)
                $severity = 'high';
                $isAnomaly = true;
                $anomalyType = 'excessive_consumption_spike';
            } elseif ($deviationPct <= -70.0) {
                // Drop of -70% or more
                $severity = 'moderate';
                $isAnomaly = true;
                $anomalyType = 'abnormal_drop_potential_tampering';
            } elseif ($deviationPct >= 40.0) {
                $severity = 'moderate';
                $isAnomaly = true;
                $anomalyType = 'elevated_consumption';
            } else {
                $severity = 'normal';
                $isAnomaly = false;
                $anomalyType = 'none';
            }
        }

        $result = [
            'meter_number' => $meterNumber,
            'utility_type' => $utilityType,
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'current_consumption' => $currentConsumption,
            'historical_average_consumption' => $historicalAverageConsumption,
            'deviation_percentage' => $deviationPct,
            'is_anomaly' => $isAnomaly,
            'anomaly_type' => $anomalyType,
            'severity' => $severity,
        ];

        $this->audit(
            action: 'utility_submeter_anomaly_scanned',
            newValues: ['meter' => $meterNumber, 'consumption' => $currentConsumption, 'anomaly' => $isAnomaly],
            impactType: 'mall_engineering',
        );

        return $result;
    }
}
