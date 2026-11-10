<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections\Reducers;

use Modules\Core\Application\Projections\ProjectionReducerInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Reducer Proyeksi Pengiriman Logistik (Logistics)
 */
class LogisticsShipmentProjectionReducer implements ProjectionReducerInterface
{
    public function supportedTopics(): array
    {
        return [
            'lgx.shipment.*',
            'lgx.shipment.delivered.v1',
            'lgx.temperature.breached.v1',
        ];
    }

    public function reduce(array $currentState, SimEventSpine $event): array
    {
        $payload = (array) $event->payload;
        $shipmentId = (string) ($payload['shipment_id'] ?? 'unknown');

        $shipments = $currentState['domains']['logistics']['shipments'] ?? [];

        if ($event->event_name === 'lgx.shipment.delivered.v1' || str_contains($event->topic, 'delivered')) {
            $shipments[$shipmentId] = [
                'status' => 'delivered',
                'tracking_number' => $payload['tracking_number'] ?? null,
                'vehicle_id' => $payload['vehicle_id'] ?? null,
                'delivered_at' => $payload['delivered_at'] ?? (string) $event->occurred_at,
            ];
            $currentState['domains']['logistics']['delivered_count'] = ($currentState['domains']['logistics']['delivered_count'] ?? 0) + 1;
        } elseif ($event->event_name === 'lgx.temperature.breached.v1' || str_contains($event->topic, 'breached')) {
            $shipments[$shipmentId]['temp_breached'] = true;
            $shipments[$shipmentId]['recorded_temp'] = $payload['recorded_temp_celsius'] ?? null;
            $currentState['domains']['logistics']['breaches_count'] = ($currentState['domains']['logistics']['breaches_count'] ?? 0) + 1;
        }

        $currentState['domains']['logistics']['shipments'] = $shipments;

        return $currentState;
    }
}
