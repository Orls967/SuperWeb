<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Logistics\Application\Queries\ControlTowerQuery;

/**
 * Control Tower dashboard for logistics_admin.
 * Provides KPI OTIF, fleet status, dwell time, COD status, margin per lane.
 */
class ControlTowerController extends Controller
{
    public function index(Request $request, ControlTowerQuery $query): View
    {
        if (! in_array($request->user()?->role, ['logistics_admin', 'admin'])) {
            abort(403, 'Akses terbatas untuk Administrator Logistik.');
        }

        $period = $request->input('period', now()->format('Y-m'));
        $data = $query->get($period);

        return view('logistics::control-tower.index', $data);
    }
}
