<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

class ProcessHubInboundScanAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEventAction,
        protected RaiseShipmentExceptionAction $raiseException
    ) {}

    /**
     * Process an inbound scan at a hub facility.
     * Automatically detects and flags missorted shipments outside their route itinerary.
     *
     * @return array{
     *     status: 'success'|'missort',
     *     is_missort: bool,
     *     message: string,
     *     shipment: Shipment,
     *     event: TrackingEvent
     * }
     */
    public function execute(Shipment $shipment, Location $hub, User $operator): array
    {
        return DB::transaction(function () use ($shipment, $hub, $operator) {
            // Lock the shipment row: refresh() re-reads committed state but does
            // not block a concurrent scan, so two operators could both pass the
            // same status checks and double-apply the transition.
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->getKey());
            $shipment->loadMissing('legs');

            // 1. Calculate all valid location IDs in the shipment's itinerary
            $allowedLocationIds = collect([$shipment->origin_location_id, $shipment->destination_location_id])
                ->merge($shipment->legs->pluck('origin_location_id'))
                ->merge($shipment->legs->pluck('destination_location_id'))
                ->filter()
                ->unique()
                ->all();

            $isMissort = ! in_array($hub->id, $allowedLocationIds, true);

            if ($isMissort) {
                // Auto-generate MISSORT exception
                if ($shipment->status->canTransitionTo(ShipmentStatus::Exception)) {
                    $shipment->transitionTo(ShipmentStatus::Exception);
                } else {
                    $shipment->status = ShipmentStatus::Exception;
                    $shipment->save();
                }

                $event = $this->recordEventAction->execute(
                    shipment: $shipment,
                    eventType: 'MISSORT',
                    locationId: $hub->id,
                    actor: $operator,
                    actorRole: 'hub_operator',
                    description: "MISSORT: Kargo salah sortir tiba di {$hub->name} (di luar itinerary resmi).",
                    payload: [
                        'scanned_hub_id' => $hub->id,
                        'scanned_hub_code' => $hub->code,
                        'allowed_hub_ids' => $allowedLocationIds,
                        'is_missort' => true,
                    ]
                );

                $this->raiseException->execute(
                    shipment: $shipment,
                    type: ExceptionType::Missort,
                    description: "Kargo salah sortir tiba di {$hub->name} ({$hub->code}), di luar itinerary resmi.",
                    reporter: $operator,
                    locationId: $hub->id,
                    dedupeKey: "missort:{$shipment->id}:{$event->id}",
                    payload: ['scanned_hub_id' => $hub->id, 'tracking_event_id' => $event->id],
                );

                return [
                    'status' => 'missort',
                    'is_missort' => true,
                    'message' => "PERINGATAN: Kargo salah sortir! Fasilitas {$hub->name} ({$hub->code}) tidak berada dalam rute pengiriman ini.",
                    'shipment' => $shipment,
                    'event' => $event,
                ];
            }

            // Normal Inbound Scan
            if ($shipment->status->canTransitionTo(ShipmentStatus::AtHub)) {
                $shipment->transitionTo(ShipmentStatus::AtHub);
            } else {
                $shipment->status = ShipmentStatus::AtHub;
                $shipment->save();
            }

            $event = $this->recordEventAction->execute(
                shipment: $shipment,
                eventType: 'HUB_INBOUND',
                locationId: $hub->id,
                actor: $operator,
                actorRole: 'hub_operator',
                description: "Kargo diterima dan dipindai inbound di fasilitas {$hub->name}.",
                payload: [
                    'scanned_hub_id' => $hub->id,
                    'scanned_hub_code' => $hub->code,
                    'scan_type' => 'inbound',
                ]
            );

            return [
                'status' => 'success',
                'is_missort' => false,
                'message' => "Kargo {$shipment->tracking_number} berhasil dipindai inbound di fasilitas {$hub->name}.",
                'shipment' => $shipment,
                'event' => $event,
            ];
        });
    }
}
