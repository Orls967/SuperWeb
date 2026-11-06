<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Services\DistrictUtilitiesManagementService;
use Modules\Egy\Application\Services\DistrictUtilityReportingService;
use Modules\Egy\Domain\Models\LeakIncident;

final class EgyWaterController extends Controller
{
    public function __construct(
        private readonly DistrictUtilitiesManagementService $utilitiesService
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->utilitiesService->getWaterNetworkOverview();

        return view('egy::water.index', $overview);
    }

    public function leaks(Request $request): View
    {
        $incidents = LeakIncident::with('network')
            ->latest('detected_at')
            ->paginate(20);

        return view('egy::water.leaks', [
            'incidents' => $incidents,
        ]);
    }

    public function exportAuditReport(DistrictUtilityReportingService $reportingService): Response
    {
        $reports = $reportingService->generateIwaWaterAuditReports();
        $headers = [
            'Network ID',
            'Network Name',
            'Zone Code',
            'System Input (L)',
            'Billed Authorized (L)',
            'Unbilled Authorized (L)',
            'Apparent Losses (L)',
            'Real Losses (L)',
            'NRW (L)',
            'NRW Bps',
            'Status',
            'SHA-256 Certificate Hash',
        ];

        $rows = array_map(function ($r) {
            return [
                $r->networkId,
                $r->networkName,
                $r->zoneCode,
                $r->systemInputVolumeL,
                $r->billedAuthorizedL,
                $r->unbilledAuthorizedL,
                $r->apparentLossesL,
                $r->realLossesL,
                $r->nonRevenueWaterL,
                $r->nrwPercentageBps,
                $r->status,
                $r->verificationHash,
            ];
        }, $reports);

        $csv = $reportingService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="iwa-water-audit-report.csv"',
        ]);
    }
}
