<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

class ProcessHubOutboundAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEventAction
    ) {}

    /**
     * Scan outbound and load a shipment onto a scheduled departure from the hub.
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
        Schedule $schedule
    ): array {
        return DB::transaction(function () use ($shipment, $hub, $operator, $schedule) {
            $shipment->refresh();

            // Validate that the schedule actually departs from this hub
            if ($schedule->origin_location_id !== $hub->id) {
                throw new \InvalidArgumentException(
                    "Jadwal keberangkatan {$schedule->schedule_number} tidak berangkat dari hub ini ({$hub->name})."
                );
            }

            // Validate schedule is not completed or cancelled
            if ($schedule->status->isTerminal()) {
                throw new \InvalidArgumentException(
                    "Jadwal keberangkatan {$schedule->schedule_number} sudah selesai atau dibatalkan."
                );
            }

            // Transition status to InTransit
            if ($shipment->status->canTransitionTo(ShipmentStatus::InTransit)) {
                $shipment->transitionTo(ShipmentStatus::InTransit);
            } else {
                $shipment->status = ShipmentStatus::InTransit;
                $shipment->save();
            }

            $event = $this->recordEventAction->execute(
                shipment: $shipment,
                eventType: 'HUB_OUTBOUND',
                locationId: $hub->id,
                actor: $operator,
                actorRole: 'hub_operator',
                description: "Kargo dipindai outbound dari fasilitas {$hub->name} dan dimuat ke jadwal trip {$schedule->schedule_number} ({$schedule->mode->label()}).",
                payload: [
                    'schedule_id' => $schedule->id,
                    'schedule_number' => $schedule->schedule_number,
                    'destination_location_id' => $schedule->destination_location_id,
                    'transport_mode' => $schedule->mode->value,
                ]
            );

            return [
                'status' => 'success',
                'message' => "Kargo {$shipment->tracking_number} berhasil dipindai outbound dan dimuat ke jadwal {$schedule->schedule_number}.",
                'shipment' => $shipment,
                'event' => $event,
            ];
        });
    }
}
