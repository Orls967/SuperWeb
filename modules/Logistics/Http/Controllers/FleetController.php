<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Domain\Models\Aircraft;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Trailer;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Uld;
use Modules\Logistics\Domain\Models\Vessel;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'trucks');

        $trucks = Truck::with(['vehicle.car.brand', 'currentLocation'])->paginate(15, ['*'], 'trucks_page');
        $trailers = Trailer::with('currentLocation')->paginate(15, ['*'], 'trailers_page');
        $vessels = Vessel::with('currentLocation')->paginate(15, ['*'], 'vessels_page');
        $aircraft = Aircraft::with('currentLocation')->paginate(15, ['*'], 'aircraft_page');
        $containers = Container::with('currentLocation')->paginate(15, ['*'], 'containers_page');
        $ulds = Uld::with('currentLocation')->paginate(15, ['*'], 'ulds_page');

        return view('logistics::fleet.index', [
            'activeTab' => $tab,
            'trucks' => $trucks,
            'trailers' => $trailers,
            'vessels' => $vessels,
            'aircraft' => $aircraft,
            'containers' => $containers,
            'ulds' => $ulds,
        ]);
    }
}
