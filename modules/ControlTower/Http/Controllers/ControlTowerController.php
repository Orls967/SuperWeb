<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;

class ControlTowerController extends Controller
{
    public function index(Request $request): View
    {
        $stocks = EchelonStock::orderBy('item_code')->limit(10)->get();
        $forecasts = DemandForecast::orderByDesc('period')->limit(10)->get();
        $promises = OrderPromise::orderByDesc('created_at')->limit(10)->get();
        $alerts = DisruptionAlert::orderByDesc('created_at')->limit(10)->get();

        return view('control_tower::index', compact('stocks', 'forecasts', 'promises', 'alerts'));
    }
}
