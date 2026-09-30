<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

use SplPriorityQueue;

class RoutePlanner
{
    /**
     * Find feasible multimodal itineraries from origin to destination.
     * Pure domain algorithm: Operates entirely in-memory over the given RouteGraph.
     *
     * @return array<int, RouteItinerary>
     */
    public function findRoutes(RouteGraph $graph, RouteRequest $request, int $maxResults = 5): array
    {
        if ($request->originLocationId === $request->destinationLocationId) {
            return [];
        }

        $allowedModes = $request->allowedModes();

        // Priority Queue ordered by arrival timestamp ascending (Min-Heap behavior)
        $queue = new class extends SplPriorityQueue
        {
            public function compare(mixed $priority1, mixed $priority2): int
            {
                // Invert comparison so smaller timestamp has higher priority
                return $priority2 <=> $priority1;
            }
        };

        // Seed initial state at origin
        $initialState = [
            'location_id' => $request->originLocationId,
            'current_time' => $request->readyAt,
            'legs' => [],
            'visited' => [$request->originLocationId => true],
            'cost' => 0,
        ];

        $queue->insert($initialState, $request->readyAt->getTimestamp());

        /** @var array<int, RouteItinerary> $foundItineraries */
        $foundItineraries = [];
        $visitedStatesCount = 0;
        $maxEvaluations = 10000;

        while (! $queue->isEmpty() && $visitedStatesCount < $maxEvaluations) {
            $visitedStatesCount++;
            $state = $queue->extract();

            $currentLocId = $state['location_id'];
            $currentTime = $state['current_time'];
            $legs = $state['legs'];
            $visited = $state['visited'];
            $currentCost = $state['cost'];

            $outgoingEdges = $graph->getOutgoingEdges($currentLocId);

            foreach ($outgoingEdges as $edge) {
                // 1. Mode constraint
                if (! in_array($edge->mode, $allowedModes, true)) {
                    continue;
                }

                // 2. Reefer constraint
                if ($request->isReefer && ! $edge->isReeferCapable) {
                    continue;
                }

                // 3. Dangerous Goods constraint
                if (! $edge->allowsDgClass($request->dgClass)) {
                    continue;
                }

                // 4. Capacity constraints
                if ($edge->availableWeightKg < $request->weightKg || $edge->availableVolumeDm3 < $request->volumeDm3) {
                    continue;
                }

                // 5. Connection & Cut-off timing constraints
                if (empty($legs)) {
                    // First leg: must depart after ready time and before/at cut-off
                    if ($edge->etd->lessThan($request->readyAt) || $edge->cutoffAt->lessThan($request->readyAt)) {
                        continue;
                    }
                } else {
                    // Subsequent leg: check transfer connection time at intermediate hub
                    $node = $graph->getNode($currentLocId);
                    $minConnMinutes = $node?->minConnectionMinutes ?? 60;
                    $earliestValidDeparture = $currentTime->copy()->addMinutes($minConnMinutes);

                    if ($edge->etd->lessThan($earliestValidDeparture) || $edge->cutoffAt->lessThan($currentTime)) {
                        continue;
                    }
                }

                $destId = $edge->destinationLocationId;

                // 6. Cycle prevention
                if (isset($visited[$destId])) {
                    continue;
                }

                $newLegs = array_merge($legs, [$edge]);
                $newCost = $currentCost + $edge->costIdr;

                // 7. Destination Reached!
                if ($destId === $request->destinationLocationId) {
                    $itinerary = new RouteItinerary(
                        legs: $newLegs,
                        departureTime: $newLegs[0]->etd,
                        arrivalTime: $edge->eta,
                        totalDurationMinutes: (int) $newLegs[0]->etd->diffInMinutes($edge->eta),
                        totalCostIdr: $newCost
                    );

                    $foundItineraries[] = $itinerary;

                    if (count($foundItineraries) >= $maxResults * 2) {
                        break 2;
                    }

                    continue;
                }

                // 8. Max transfers pruning
                if (count($newLegs) >= $request->maxTransfers) {
                    continue;
                }

                // 9. Enqueue next hop
                $newVisited = $visited;
                $newVisited[$destId] = true;

                $nextState = [
                    'location_id' => $destId,
                    'current_time' => $edge->eta,
                    'legs' => $newLegs,
                    'visited' => $newVisited,
                    'cost' => $newCost,
                ];

                $queue->insert($nextState, $edge->eta->getTimestamp());
            }
        }

        // Sort itineraries: primary by ETA ascending, secondary by cost ascending, tertiary by duration
        usort($foundItineraries, function (RouteItinerary $a, RouteItinerary $b) {
            $etaComparison = $a->arrivalTime <=> $b->arrivalTime;
            if ($etaComparison !== 0) {
                return $etaComparison;
            }

            $costComparison = $a->totalCostIdr <=> $b->totalCostIdr;
            if ($costComparison !== 0) {
                return $costComparison;
            }

            return $a->totalDurationMinutes <=> $b->totalDurationMinutes;
        });

        // Deduplicate and return top results
        return array_slice($foundItineraries, 0, $maxResults);
    }
}
