<?php

declare(strict_types=1);

namespace Modules\Plm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Plm\Domain\Models\ChangeOrder;
use Modules\Plm\Domain\Models\PlmProject;

class PlmController extends Controller
{
    public function index(Request $request)
    {
        $projects = PlmProject::with('eboms')->latest()->take(20)->get();
        $changeOrders = ChangeOrder::latest()->take(20)->get();

        return view('plm::plm.index', [
            'projects' => $projects,
            'changeOrders' => $changeOrders,
        ]);
    }
}
