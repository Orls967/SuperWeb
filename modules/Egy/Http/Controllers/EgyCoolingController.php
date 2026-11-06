<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Services\DistrictUtilitiesManagementService;
use Modules\Egy\Application\Services\DistrictUtilityReportingService;

final class EgyCoolingController extends Controller
{
    public function __construct(
        private readonly DistrictUtilitiesManagementService $utilitiesService
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->utilitiesService->getDistrictCoolingOverview();

        return view('egy::cooling.index', $overview);
    }

    public function exportPerformanceReport(DistrictUtilityReportingService $reportingService): Response
    {
        $reports = $reportingService->generateCoolingPerformanceReports();
        $headers = [
            'Plant ID',
            'Plant Name',
            'Plant Code',
            'Installed Capacity (RT)',
            'Total Chillers',
            'Active Chillers',
            'Cooling Delivered (RTh)',
            'Electricity Consumed (kWh)',
            'Average kW/RT (bps)',
            'Average COP (bps)',
            'Operational Status',
            'SHA-256 Certificate Hash',
        ];

        $rows = array_map(function ($r) {
            return [
                $r->plantId,
                $r->plantName,
                $r->plantCode,
                $r->totalInstalledCapacityRt,
                $r->totalChillersCount,
                $r->activeChillersCount,
                $r->totalCoolingDeliveredRth,
                $r->totalElectricityConsumedKwh,
                $r->averageKwPerTonBps,
                $r->averageCopBps,
                $r->overallStatus,
                $r->verificationHash,
            ];
        }, $reports);

        $csv = $reportingService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="district-cooling-performance-report.csv"',
        ]);
    }
}
