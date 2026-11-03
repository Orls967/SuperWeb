<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions\Reverse;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Application\Actions\RecordTrackingEventAction;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidShipmentTransitionException;
use Modules\Logistics\Domain\Models\Shipment;

class TransitionReverseShipmentAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordTrackingEvent,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    public function execute(
        Shipment $shipment,
        ShipmentStatus $targetStatus,
        ?User $actor = null,
        ?int $locationId = null,
        ?string $note = null,
        array $payload = []
    ): Shipment {
        return DB::transaction(function () use ($shipment, $targetStatus, $actor, $locationId, $note, $payload) {
            $locked = Shipment::where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_reverse) {
                throw new \InvalidArgumentException("Shipment {$locked->id} bukan shipment reverse.");
            }

            if (! $locked->status->canTransitionTo($targetStatus)) {
                throw new InvalidShipmentTransitionException(
                    "Transisi tidak valid dari status {$locked->status->value} ke {$targetStatus->value}"
                );
            }

            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();
            $updateData = ['status' => $targetStatus];

            if ($targetStatus === ShipmentStatus::PickedUp) {
                $updateData['picked_up_at'] = $now;
            } elseif ($targetStatus === ShipmentStatus::DeliveredToPlant || $targetStatus === ShipmentStatus::Received) {
                $updateData['delivered_at'] = $now;
            }

            $locked->update($updateData);

            $this->recordTrackingEvent->execute(
                shipment: $locked,
                eventType: "reverse.{$targetStatus->value}",
                locationId: $locationId ?? $locked->destination_facility_id,
                actor: $actor,
                actorRole: $actor ? $actor->role : 'operator',
                description: $note ?? "Status reverse shipment berubah menjadi {$targetStatus->label()}",
                payload: array_merge($payload, [
                    'previous_status' => $locked->status->value,
                    'new_status' => $targetStatus->value,
                ]),
                occurredAt: $now
            );

            return $locked;
        });
    }
}

