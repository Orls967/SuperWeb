<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ControlTower\Application\Actions\DetectSupplyChainBottleneckAlertAction;
use Modules\ControlTower\Application\Actions\ExecuteAutonomousReallocationPlaybookAction;
use Modules\ControlTower\Application\Actions\RunConsensusSopHoltWintersForecastAction;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;

class ControlTowerAutonomousController extends Controller
{
    public function index(): mixed
    {
        $forecasts = DemandForecast::latest()->take(10)->get();
        $alerts = DisruptionAlert::where('status', 'active')->latest()->take(10)->get();
        $stocks = EchelonStock::latest()->take(15)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'forecasts' => $forecasts,
                'active_alerts' => $alerts,
                'echelon_stocks' => $stocks,
            ]);
        }

        return view('control_tower::control_tower_cockpit', compact('forecasts', 'alerts', 'stocks'));
    }

    public function runForecast(
        Request $request,
        RunConsensusSopHoltWintersForecastAction $action
    ): JsonResponse {
        $data = $request->validate([
            'item_code' => 'required|string|max:32',
            'period' => 'required|string|size:7',
            'historical_demands' => 'required|array|min:3',
            'historical_demands.*' => 'integer|min:0',
            'alpha' => 'nullable|numeric|between:0.01,0.99',
            'beta' => 'nullable|numeric|between:0.01,0.99',
            'override_reason' => 'nullable|string|max:255',
        ]);

        $result = $action->execute(
            $data['item_code'],
            $data['period'],
            $data['historical_demands'],
            (float) ($data['alpha'] ?? 0.3),
            (float) ($data['beta'] ?? 0.1),
            $data['override_reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Consensus Holt-Winters forecast computed.',
            'data' => $result,
        ], 201);
    }

    public function detectBottlenecks(
        Request $request,
        DetectSupplyChainBottleneckAlertAction $action
    ): JsonResponse {
        $itemCode = $request->query('item_code');
        $alerts = $action->execute($itemCode ? (string) $itemCode : null);

        return response()->json([
            'success' => true,
            'detected_alerts_count' => count($alerts),
            'alerts' => $alerts,
        ]);
    }

    public function reallocateStock(
        Request $request,
        ExecuteAutonomousReallocationPlaybookAction $action
    ): JsonResponse {
        $data = $request->validate([
            'item_code' => 'required|string|max:32',
            'from_node' => 'required|string|max:32',
            'to_node' => 'required|string|max:32|different:from_node',
            'transfer_qty' => 'required|integer|min:1',
        ]);

        $result = $action->execute(
            $data['item_code'],
            $data['from_node'],
            $data['to_node'],
            (int) $data['transfer_qty']
        );

        return response()->json([
            'success' => true,
            'message' => 'Autonomous inventory reallocation executed successfully.',
            'data' => $result,
        ]);
    }
}
