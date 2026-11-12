<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\ComplianceEscalationService;

class ComplianceCalendarController extends Controller
{
    public function __construct(
        protected readonly ComplianceEscalationService $complianceService
    ) {}

    public function index(Request $request): View
    {
        $scan = $this->complianceService->scanAndEscalate();

        return view('enterprise_finance::compliance_calendar_v2', [
            'scanSummary' => $scan,
        ]);
    }
}
