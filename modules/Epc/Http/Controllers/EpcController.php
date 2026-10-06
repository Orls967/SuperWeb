<?php

namespace Modules\Epc\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;

class EpcController extends Controller
{
    public function index(Request $request, EpcService $service)
    {
        $projects = EpcProject::with('wbsNodes')->latest()->take(20)->get();
        $certificates = EpcProgressCertificate::with('project')->latest()->take(20)->get();
        $capitalizations = EpcCipCapitalization::with('project')->latest()->take(20)->get();
        $audit = $service->auditEpc();

        return view('epc::epc.index', compact('projects', 'certificates', 'capitalizations', 'audit'));
    }
}
