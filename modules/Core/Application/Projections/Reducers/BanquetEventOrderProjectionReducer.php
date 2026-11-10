<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections\Reducers;

use Modules\Core\Application\Projections\ProjectionReducerInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Reducer Proyeksi Banquet Event Order & MICE (Hotel)
 */
class BanquetEventOrderProjectionReducer implements ProjectionReducerInterface
{
    public function supportedTopics(): array
    {
        return [
            'hotel.banquet.*',
            'hotel.banquet.beo_finalized.v1',
        ];
    }

    public function reduce(array $currentState, SimEventSpine $event): array
    {
        $payload = (array) $event->payload;
        $beoId = (string) ($payload['beo_id'] ?? 'unknown_beo');

        $banquets = $currentState['domains']['hotel']['banquets'] ?? [];
        $banquets[$beoId] = [
            'status' => 'finalized',
            'event_date' => $payload['event_date'] ?? null,
            'attendee_count' => (int) ($payload['attendee_count'] ?? 0),
            'guaranteed_pax' => (int) ($payload['guaranteed_pax'] ?? 0),
            'total_amount' => (int) ($payload['total_amount'] ?? 0),
            'last_sync' => (string) $event->occurred_at,
        ];

        $currentState['domains']['hotel']['banquets'] = $banquets;
        $currentState['domains']['hotel']['finalized_beo_count'] = count($banquets);

        return $currentState;
    }
}
