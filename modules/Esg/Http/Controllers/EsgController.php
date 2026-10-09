<?php

namespace Modules\Esg\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Esg\Application\Services\EsgService;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\EsgEmission;
use Modules\Esg\Domain\Models\OffsetRetirement;
use Modules\Esg\Domain\Models\SupplierScore;

class EsgController extends Controller
{
    public function index(Request $request, EsgService $service)
    {
        $emissions = EsgEmission::latest()->take(20)->get();
        $credits = CarbonCredit::latest()->take(20)->get();
        $retirements = OffsetRetirement::with('credit')->latest()->take(20)->get();
        $scores = SupplierScore::latest()->take(20)->get();
        $audit = $service->auditEsg();

        return view('esg::esg.index', compact('emissions', 'credits', 'retirements', 'scores', 'audit'));
    }
}
