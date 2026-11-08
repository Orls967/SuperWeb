<?php

declare(strict_types=1);

namespace Modules\Marine\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\MangroveRestorationProject;

class AquacultureExportController extends Controller
{
    public function index(Request $request)
    {
        $batches = AquacultureBatch::with('hatchery')->latest('id')->paginate(15);

        return view('marine::aquaculture.index', compact('batches'));
    }

    public function traceability(Request $request)
    {
        $exportLots = AquacultureExportLot::with(['harvestOperation.batch', 'traceabilityNodes'])->latest('id')->paginate(15);

        return view('marine::aquaculture.traceability', compact('exportLots'));
    }

    public function escrow(Request $request)
    {
        $settlements = AquacultureEscrowSettlement::with('order')->latest('id')->paginate(15);

        return view('marine::aquaculture.escrow', compact('settlements'));
    }

    public function blueCarbon(Request $request)
    {
        $projects = MangroveRestorationProject::with('assessments')->latest('id')->paginate(15);
        $totalCreditsIssued = BlueCarbonCreditIssuance::sum('carbon_credits_tco2e');

        return view('marine::aquaculture.blue_carbon', compact('projects', 'totalCreditsIssued'));
    }
}
