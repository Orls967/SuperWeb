<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Services;

use Modules\Mobility\Ev\Application\DTOs\SmartChargingPlanDTO;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSmartChargingPlan;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Modules\Shared\Domain\Algorithms\Flow\DinicMaxFlow;

class SmartChargingDinicService
{
    /**
     * @param  array<string, array{charger_code: string, requested_wh: int}>  $requests
     */
    public function allocateSlotPower(EvStation $station, array $requests): SmartChargingPlanDTO
    {
        // 1. Sort request keys for determinism (PR3)
        ksort($requests);

        $chargers = EvCharger::where('station_id', $station->id)->get()->keyBy('charger_code');
        $chargerCodes = array_values(array_unique(array_column($requests, 'charger_code')));
        sort($chargerCodes);

        $siteCapWh = (int) ($station->max_grid_power_kw * 250);

        // 2. Vertex Index Mapping
        // 0: Source S
        // 1: Station
        $source = 0;
        $stationNode = 1;

        $chargerNodes = [];
        $nodeCounter = 2;
        foreach ($chargerCodes as $cc) {
            $chargerNodes[$cc] = $nodeCounter++;
        }

        $sessionNodes = [];
        foreach (array_keys($requests) as $sessCode) {
            $sessionNodes[$sessCode] = $nodeCounter++;
        }

        $sink = $nodeCounter++;
        $vertexCount = $nodeCounter;

        $dinic = new DinicMaxFlow($vertexCount);

        // 3. Add Edges
        // S -> Station
        $dinic->addEdge($source, $stationNode, $siteCapWh);

        // Station -> Chargers
        foreach ($chargerCodes as $cc) {
            $chg = $chargers->get($cc);
            $chgCapWh = $chg !== null ? (int) ($chg->max_power_kw * 250) : DinicMaxFlow::INF;
            $dinic->addEdge($stationNode, $chargerNodes[$cc], $chgCapWh);
        }

        // Chargers -> Sessions & Sessions -> Sink
        $sessionEdgeIndices = [];
        foreach ($requests as $sessCode => $req) {
            $cc = $req['charger_code'];
            $reqWh = (int) $req['requested_wh'];

            $dinic->addEdge($chargerNodes[$cc], $sessionNodes[$sessCode], $reqWh);
            $sinkEdgeIdx = $dinic->addEdge($sessionNodes[$sessCode], $sink, $reqWh);
            $sessionEdgeIndices[$sessCode] = $sinkEdgeIdx;
        }

        // 4. Solve Max-Flow
        $flowResult = $dinic->solve($source, $sink);

        $allocations = [];
        $deficits = [];
        $totalAllocated = 0;
        $totalDeficit = 0;

        foreach ($requests as $sessCode => $req) {
            $reqWh = (int) $req['requested_wh'];
            $edgeIdx = $sessionEdgeIndices[$sessCode];
            $allocatedWh = $flowResult->flowPerEdge[$edgeIdx] ?? 0;
            $deficitWh = max(0, $reqWh - $allocatedWh);

            $allocations[$sessCode] = $allocatedWh;
            $deficits[$sessCode] = $deficitWh;
            $totalAllocated += $allocatedWh;
            $totalDeficit += $deficitWh;
        }

        $certificate = [
            'max_flow_wh' => $flowResult->maxFlow,
            'cut_capacity_wh' => $flowResult->maxFlow,
            'source_cut_set' => $flowResult->sourceCutSet,
        ];

        return new SmartChargingPlanDTO(
            allocations: $allocations,
            deficits: $deficits,
            totalAllocatedWh: $totalAllocated,
            totalDeficitWh: $totalDeficit,
            certificate: $certificate
        );
    }

    public function persistPlan(EvStation $station, string $timeSlot, SmartChargingPlanDTO $plan): EvSmartChargingPlan
    {
        return EvSmartChargingPlan::create([
            'plan_uuid' => 'PLAN-DINIC-'.uniqid(),
            'station_id' => $station->id,
            'time_slot' => $timeSlot,
            'allocated_power_kw' => (int) div_round_wh($plan->totalAllocatedWh()),
            'certificate_json' => $plan->certificate,
            'status' => 'VALID',
        ]);
    }
}

if (! function_exists('div_round_wh')) {
    function div_round_wh(int $wh): int
    {
        return intdiv($wh, 250);
    }
}
