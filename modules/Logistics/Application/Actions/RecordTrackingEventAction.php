<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

class RecordTrackingEventAction
{
    /**
     * Atomically append an immutable cryptographic tracking event to the shipment's chain of custody.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Shipment $shipment,
        string $eventType,
        ?int $locationId = null,
        ?User $actor = null,
        ?string $actorRole = null,
        ?string $description = null,
        array $payload = [],
        ?CarbonInterface $occurredAt = null
    ): TrackingEvent {
        return DB::transaction(function () use (
            $shipment,
            $eventType,
            $locationId,
            $actor,
            $actorRole,
            $description,
            $payload,
            $occurredAt
        ) {
            $occurredAtTime = $occurredAt ? Carbon::parse($occurredAt) : Carbon::now();

            // Lock latest tracking event for this shipment
            $lastEvent = TrackingEvent::where('shipment_id', $shipment->id)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            if ($lastEvent) {
                $sequence = $lastEvent->sequence + 1;
                $prevHash = $lastEvent->hash;
            } else {
                $sequence = 1;
                $prevHash = TrackingEvent::genesisHash($shipment->id);
            }

            $hash = TrackingEvent::calculateHash(
                prevHash: $prevHash,
                sequence: $sequence,
                eventType: $eventType,
                payload: $payload,
                occurredAt: $occurredAtTime
            );

            return TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'location_id' => $locationId,
                'actor_id' => $actor?->id,
                'actor_role' => $actorRole ?? ($actor ? $actor->role : 'system'),
                'description' => $description,
                'payload' => $payload,
                'occurred_at' => $occurredAtTime,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_at' => now(),
            ]);
        });
    }
}
