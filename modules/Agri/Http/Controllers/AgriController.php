<?php

namespace Modules\Agri\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Agri\Application\Services\AgriService;
use Modules\Agri\Domain\Models\AgriColdChainLog;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;

class AgriController extends Controller
{
    public function index(Request $request, AgriService $service)
    {
        $farmers = AgriFarmer::latest()->take(20)->get();
        $contracts = AgriContract::with('farmer')->latest()->take(20)->get();
        $batches = AgriCollectionBatch::with('contract.farmer')->latest()->take(20)->get();
        $coldLogs = AgriColdChainLog::latest()->take(20)->get();
        $audit = $service->auditAgri();

        return view('agri::agri.index', compact('farmers', 'contracts', 'batches', 'coldLogs', 'audit'));
    }
}
