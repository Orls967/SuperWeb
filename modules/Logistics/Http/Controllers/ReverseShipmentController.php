<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Logistics\Application\Actions\Reverse\CreateReverseShipmentAction;
use Modules\Logistics\Application\Actions\Reverse\TransitionReverseShipmentAction;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Shared\Domain\Algorithms\Flow\MinCostFlow;
use Modules\Shared\Domain\Algorithms\Flow\MinCostFlowCertificateVerifier;

class ReverseShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Shipment::query()->where('is_reverse', true);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($reverseType = $request->input('reverse_type')) {
            $query->where('reverse_type', $reverseType);
        }

        $shipments = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $stats = [
            'total_reverse' => Shipment::where('is_reverse', true)->count(),
            'requested' => Shipment::where('is_reverse', true)->where('status', ShipmentStatus::Requested->value)->count(),
            'at_hub' => Shipment::where('is_reverse', true)->where('status', ShipmentStatus::AtHub->value)->count(),
            'delivered_to_plant' => Shipment::where('is_reverse', true)->where('status', ShipmentStatus::DeliveredToPlant->value)->count(),
            'received' => Shipment::where('is_reverse', true)->where('status', ShipmentStatus::Received->value)->count(),
            'rejected' => Shipment::where('is_reverse', true)->where('status', ShipmentStatus::Rejected->value)->count(),
        ];

        return view('logistics::reverse.shipments', [
            'shipments' => $shipments,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentType' => $reverseType,
        ]);
    }

    public function show(Shipment $shipment): View|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json([
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'is_reverse' => (bool) $shipment->is_reverse,
                'reverse_type' => $shipment->reverse_type,
                'manifest_number' => $shipment->reverse_manifest_number,
                'chain_hash' => $shipment->reverse_chain_hash,
                'material_code' => $shipment->material_code,
                'weight_grams' => $shipment->weight_grams,
                'quality_grade' => $shipment->quality_grade,
                'carrier_id' => $shipment->carrier_id,
                'carrier_cost_idr' => $shipment->carrier_cost_idr,
                'supplier_party_id' => $shipment->supplier_party_id,
                'plant_id' => $shipment->plant_id,
                'plant_receipt_id' => $shipment->plant_receipt_id,
            ]);
        }

        return view('logistics::reverse.show', [
            'shipment' => $shipment,
        ]);
    }

    public function store(Request $request, CreateReverseShipmentAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sender_id' => ['required', 'integer'],
            'recipient_id' => ['required', 'integer'],
            'origin_hub_id' => ['nullable', 'integer'],
            'destination_hub_id' => ['nullable', 'integer'],
            'reverse_type' => ['required', 'string', 'in:return,warranty,recycling,pallet_recovery,recall'],
            'material_code' => ['required', 'string', 'max:64'],
            'quality_grade' => ['required', 'string', 'in:A,B,C,SCRAP'],
            'weight_grams' => ['required', 'integer', 'min:1'],
            'plant_id' => ['required', 'string', 'max:64'],
            'carrier_id' => ['nullable', 'integer'],
            'carrier_cost_idr' => ['nullable', 'integer', 'min:0'],
            'supplier_party_id' => ['nullable', 'string', 'max:64'],
            'pickup_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
        ]);

        $shipment = $action->execute(
            senderId: (int) $validated['sender_id'],
            recipientId: (int) $validated['recipient_id'],
            reverseType: $validated['reverse_type'],
            materialCode: $validated['material_code'],
            qualityGrade: $validated['quality_grade'],
            weightGrams: (int) $validated['weight_grams'],
            plantId: (string) $validated['plant_id'],
            originHubId: isset($validated['origin_hub_id']) ? (int) $validated['origin_hub_id'] : null,
            destinationHubId: isset($validated['destination_hub_id']) ? (int) $validated['destination_hub_id'] : null,
            carrierId: isset($validated['carrier_id']) ? (int) $validated['carrier_id'] : null,
            carrierCostIdr: isset($validated['carrier_cost_idr']) ? (int) $validated['carrier_cost_idr'] : 0,
            supplierPartyId: $validated['supplier_party_id'] ?? null,
            pickupAddress: $validated['pickup_address'] ?? 'Origin Facility',
            deliveryAddress: $validated['delivery_address'] ?? 'Destination Plant Facility',
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'shipment' => $shipment,
                'message' => "Shipment reverse {$shipment->tracking_number} berhasil dibuat.",
            ], 201);
        }

        return redirect()->route('logistics.reverse.shipments.index')
            ->with('success', "Shipment reverse {$shipment->tracking_number} berhasil dibuat.");
    }

    public function transition(Request $request, Shipment $shipment, TransitionReverseShipmentAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'target_status' => ['required', 'string', 'in:picked_up,at_hub,delivered_to_plant,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:128'],
        ]);

        $targetEnum = ShipmentStatus::from($validated['target_status']);
        $actor = $request->user();

        $updated = $action->execute(
            shipment: $shipment,
            targetStatus: $targetEnum,
            actor: $actor,
            notes: $validated['notes'] ?? null,
            location: $validated['location'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'shipment' => $updated,
                'message' => "Status shipment reverse diperbarui ke {$updated->status}.",
            ]);
        }

        return back()->with('success', "Status shipment reverse diperbarui ke {$updated->status}.");
    }

    public function optimize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.id' => ['required', 'string'],
            'sources.*.supply' => ['required', 'integer', 'min:1'],
            'plants' => ['required', 'array', 'min:1'],
            'plants.*.id' => ['required', 'string'],
            'plants.*.demand' => ['required', 'integer', 'min:1'],
            'routes' => ['required', 'array', 'min:1'],
            'routes.*.from' => ['required', 'string'],
            'routes.*.to' => ['required', 'string'],
            'routes.*.cost' => ['required', 'integer', 'min:0'],
            'routes.*.capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $nodeMap = [];
        $nodeIndex = 0;
        $getNodeId = function (string $name) use (&$nodeMap, &$nodeIndex): int {
            if (! isset($nodeMap[$name])) {
                $nodeMap[$name] = $nodeIndex++;
            }

            return $nodeMap[$name];
        };

        $sourceNode = $getNodeId('__SUPERSOURCE__');
        $sinkNode = $getNodeId('__SUPERSINK__');

        $totalSupply = 0;
        foreach ($validated['sources'] as $s) {
            $sId = $getNodeId((string) $s['id']);
            $supply = (int) $s['supply'];
            $totalSupply += $supply;
        }

        $totalDemand = 0;
        foreach ($validated['plants'] as $p) {
            $pId = $getNodeId((string) $p['id']);
            $demand = (int) $p['demand'];
            $totalDemand += $demand;
        }

        if ($totalSupply !== $totalDemand) {
            return response()->json([
                'error' => 'Total supply must equal total demand for closed network',
                'total_supply' => $totalSupply,
                'total_demand' => $totalDemand,
            ], 422);
        }

        $mcf = new MinCostFlow(count($nodeMap) + 2);

        foreach ($validated['sources'] as $s) {
            $u = $getNodeId((string) $s['id']);
            $mcf->addEdge($sourceNode, $u, (int) $s['supply'], 0);
        }

        foreach ($validated['plants'] as $p) {
            $v = $getNodeId((string) $p['id']);
            $mcf->addEdge($v, $sinkNode, (int) $p['demand'], 0);
        }

        $routeIndexMap = [];
        foreach ($validated['routes'] as $r) {
            $u = $getNodeId((string) $r['from']);
            $v = $getNodeId((string) $r['to']);
            $cost = (int) $r['cost'];
            $cap = isset($r['capacity']) ? (int) $r['capacity'] : $totalSupply;
            $edgeIdx = $mcf->addEdge($u, $v, $cap, $cost);
            $routeIndexMap[$edgeIdx] = $r;
        }

        $result = $mcf->solve($sourceNode, $sinkNode, $totalSupply);

        $verifier = new MinCostFlowCertificateVerifier;
        $isDualCertified = $verifier->verify(
            $mcf->getEdges(),
            $mcf->getVertexCount(),
            $sourceNode,
            $sinkNode,
            $result
        );

        $allocations = [];
        foreach ($routeIndexMap as $edgeIdx => $r) {
            $flow = $result->flowPerEdge[$edgeIdx] ?? 0;
            if ($flow > 0) {
                $allocations[] = [
                    'from' => $r['from'],
                    'to' => $r['to'],
                    'allocated_flow' => $flow,
                    'unit_cost' => (int) $r['cost'],
                    'total_cost' => $flow * (int) $r['cost'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'optimal' => $isDualCertified,
            'total_flow' => $result->totalFlow,
            'total_cost' => $result->totalCost,
            'allocations' => $allocations,
            'certificate' => [
                'is_valid' => $isDualCertified,
                'potentials' => $result->nodePotentials,
            ],
        ]);
    }

    public function ledger(Request $request, ?LedgerService $ledgerService = null): View
    {
        $ledgerService = $ledgerService ?? app(LedgerService::class);

        $accounts = [
            'mfg_recycled_clearing' => (int) ($ledgerService->getCachedBalance('mfg:recycled_material_clearing:IDR') ?? 0),
            'inv_materials' => (int) ($ledgerService->getCachedBalance('inv:materials:IDR') ?? 0),
            'lgx_carrier_cost' => (int) ($ledgerService->getCachedBalance('lgx:carrier_cost:IDR') ?? 0),
            'lgx_carrier_hold' => (int) ($ledgerService->getCachedBalance('lgx:carrier_payable_on_hold:IDR') ?? 0),
        ];

        $recentReverseShipments = Shipment::where('is_reverse', true)
            ->whereNotNull('plant_receipt_id')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('logistics::reverse.ledger', [
            'accounts' => $accounts,
            'recentReverseShipments' => $recentReverseShipments,
        ]);
    }
}
