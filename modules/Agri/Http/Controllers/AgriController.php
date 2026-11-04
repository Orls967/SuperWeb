<?php

declare(strict_types=1);

namespace Modules\Agri\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Agri\Application\Services\AgriService;
use Modules\Agri\Domain\Models\AgriColdChainLog;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Agri\Domain\Models\Ndvi\AgriSatelliteScan;
use Modules\Governance\Contracts\ProposalDirectory;

class AgriController extends Controller
{
    public function index(Request $request, AgriService $service, ProposalDirectory $proposalDirectory)
    {
        $farmers = AgriFarmer::latest()->take(20)->get();
        $contracts = AgriContract::with('farmer')->latest()->take(20)->get();
        $batches = AgriCollectionBatch::with('contract.farmer')->latest()->take(20)->get();
        $coldLogs = AgriColdChainLog::latest()->take(20)->get();
        $audit = $service->auditAgri();
        $satelliteScans = AgriSatelliteScan::latest()->take(10)->get();
        $financingInstallments = AgriFinancingInstallment::latest()->take(10)->get();
        $daoProposals = $proposalDirectory->getRecentProposals(10);

        return view('agri::agri.index', compact(
            'farmers',
            'contracts',
            'batches',
            'coldLogs',
            'audit',
            'satelliteScans',
            'financingInstallments',
            'daoProposals'
        ));
    }
}
