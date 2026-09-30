<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

class ProcessHubSortAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEventAction
    ) {}

    /**
     * Sort a shipment at the hub facility into a specific lane, bin, or destination bay.
     *
     * @return array{
     *     status: 'success',
     *     message: string,
     *     shipment: Shipment,
     *     event: TrackingEvent
     * }
     */
    public function execute(
        Shipment $shipment,
        Location $hub,
        User $operator,
        string $sortBay,
        ?int $nextLocationId = null
    ): array {
        return DB::transaction(function () use ($shipment, $hub, $operator, $sortBay, $nextLocationId) {
            $shipment->refresh();

            $nextLocation = $nextLocationId ? Location::find($nextLocationId) : null;
            $nextName = $nextLocation ? " menuju {$nextLocation->name}" : '';

            $event = $this->recordEventAction->execute(
                shipment: $shipment,
                eventType: 'SORTED',
                locationId: $hub->id,
                actor: $operator,
                actorRole: 'hub_operator',
                description: "Kargo disortir di {$hub->name} ke jalur/bin: {$sortBay}{$nextName}.",
                payload: [
                    'hub_id' => $hub->id,
                    'sort_bay' => $sortBay,
                    'next_location_id' => $nextLocationId,
                ]
            );

            return [
                'status' => 'success',
                'message' => "Kargo {$shipment->tracking_number} berhasil disortir ke {$sortBay}.",
                'shipment' => $shipment,
                'event' => $event,
            ];
        });
    }
}
