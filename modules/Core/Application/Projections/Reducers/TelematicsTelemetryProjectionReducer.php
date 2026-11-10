<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections\Reducers;

use Modules\Core\Application\Projections\ProjectionReducerInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Reducer Proyeksi Telematika & EV (AutoServe / Ev)
 */
class TelematicsTelemetryProjectionReducer implements ProjectionReducerInterface
{
    public function supportedTopics(): array
    {
        return [
            'auto.telematics.*',
            'auto.ev.*',
        ];
    }

    public function reduce(array $currentState, SimEventSpine $event): array
    {
        $payload = (array) $event->payload;
        $vin = (string) ($payload['vin'] ?? 'unknown_vin');

        $fleet = $currentState['domains']['telematics']['fleet'] ?? [];

        if (str_contains($event->topic, 'ev') || str_contains($event->event_name, 'ev')) {
            $fleet[$vin]['battery_soh'] = $payload['battery_soh_percent'] ?? null;
            $fleet[$vin]['kwh_consumed'] = ($fleet[$vin]['kwh_consumed'] ?? 0) + (float) ($payload['kwh_consumed'] ?? 0);
            $fleet[$vin]['last_session_id'] = $payload['session_id'] ?? null;
        } else {
            $fleet[$vin]['last_anomaly'] = $payload['anomaly_type'] ?? null;
            $fleet[$vin]['severity'] = $payload['severity'] ?? 'normal';
        }

        $currentState['domains']['telematics']['fleet'] = $fleet;
        $currentState['domains']['telematics']['monitored_units_count'] = count($fleet);

        return $currentState;
    }
}

