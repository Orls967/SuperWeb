<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteEdge;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteGraph;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteItinerary;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteNode;
use Modules\Logistics\Domain\Services\RoutePlanner\RoutePlanner;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteRequest;

class PlanShipmentRouteAction
{
    public function __construct(
        private readonly RoutePlanner $planner
    ) {}

    /**
     * Plan feasible itineraries for a shipment and optionally persist the best itinerary legs.
     *
     * @return array<int, RouteItinerary>
     */
    public function execute(Shipment $shipment, bool $persistBestItinerary = true): array
    {
        // 1. Build in-memory graph from locations and active schedules
        $graph = $this->buildGraph();

        // 2. Determine shipment specific constraints
        $isReefer = false;
        $dgClass = null;

        foreach ($shipment->packages as $pkg) {
            if ($pkg->temp_min_c10 !== null || $pkg->temp_max_c10 !== null) {
                $isReefer = true;
            }
            if (! empty($pkg->dg_class)) {
                $dgClass = $pkg->dg_class;
            }
        }

        $readyAt = $shipment->booked_at ? Carbon::parse($shipment->booked_at) : Carbon::now();
        $weightKg = max(1.0, ((float) $shipment->total_chargeable_weight_g) / 1000);

        // 3. Construct RouteRequest
        $request = new RouteRequest(
            originLocationId: $shipment->origin_location_id,
            destinationLocationId: $shipment->destination_location_id,
            serviceLevel: $shipment->service_level,
            readyAt: $readyAt,
            weightKg: $weightKg,
            isReefer: $isReefer,
            dgClass: $dgClass
        );

        // 4. Run pure domain RoutePlanner algorithm
        $itineraries = $this->planner->findRoutes($graph, $request);

        // 5. Persist best route legs if requested
        if ($persistBestItinerary && ! empty($itineraries)) {
            $best = $itineraries[0];

            DB::transaction(function () use ($shipment, $best) {
                // Remove existing pending legs
                ShipmentLeg::where('shipment_id', $shipment->id)
                    ->where('status', 'pending')
                    ->delete();

                foreach ($best->legs as $index => $leg) {
                    ShipmentLeg::create([
                        'shipment_id' => $shipment->id,
                        'schedule_id' => $leg->scheduleId,
                        'leg_sequence' => $index + 1,
                        'mode' => $leg->mode,
                        'origin_location_id' => $leg->originLocationId,
                        'destination_location_id' => $leg->destinationLocationId,
                        'estimated_departure' => $leg->etd,
                        'estimated_arrival' => $leg->eta,
                        'status' => 'pending',
                    ]);
                }
            });
        }

        return $itineraries;
    }

    /**
     * Load locations and operational schedules into an in-memory RouteGraph.
     */
    public function buildGraph(): RouteGraph
    {
        $graph = new RouteGraph;

        $locations = Location::where('is_active', true)->get();
        foreach ($locations as $loc) {
            $graph->addNode(new RouteNode(
                locationId: $loc->id,
                code: $loc->code,
                name: $loc->name,
                minConnectionMinutes: $loc->min_connection_minutes ?: 60
            ));
        }

        $schedules = Schedule::whereIn('status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value])
            ->where('etd', '>=', Carbon::now()->subHours(2))
            ->get();

        foreach ($schedules as $sched) {
            $isReefer = false;
            // Check asset reefer capability if applicable
            if ($sched->asset && method_exists($sched->asset, 'isReefer') && $sched->asset->isReefer()) {
                $isReefer = true;
            }

            $graph->addEdge(new RouteEdge(
                scheduleId: $sched->id,
                scheduleNumber: $sched->schedule_number,
                originLocationId: $sched->origin_location_id,
                destinationLocationId: $sched->destination_location_id,
                mode: $sched->mode,
                etd: Carbon::parse($sched->etd),
                eta: Carbon::parse($sched->eta),
                cutoffAt: Carbon::parse($sched->cutoff_at),
                costIdr: 0,
                isReeferCapable: $isReefer,
                availableWeightKg: (float) $sched->remainingWeightKg()->toFloat(),
                availableVolumeDm3: $sched->remainingVolumeDm3(),
                availableTeu: $sched->remainingTeu(),
                availableUldPositions: $sched->remainingUldPositions()
            ));
        }

        return $graph;
    }
}
