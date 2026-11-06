<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Services\DistrictUtilitiesManagementService;
use Modules\Egy\Application\Services\DistrictUtilityReportingService;

final class EgyWasteController extends Controller
{
    public function __construct(
        private readonly DistrictUtilitiesManagementService $utilitiesService
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->utilitiesService->getWasteManagementOverview();

        return view('egy::waste.index', $overview);
    }

    public function exportProductionReport(DistrictUtilityReportingService $reportingService): Response
    {
        $reports = $reportingService->generateBiogasWteReports();
        $headers = [
            'Facility ID',
            'Facility Name',
            'Facility Code',
            'Waste Received (kg)',
            'Biogas Produced (m3)',
            'Electricity Generated (kWh)',
            'Tipping Fee Revenue (IDR)',
            'Avoided Emissions (kg CO2e)',
            'Intake Batches',
            'Facility Status',
            'SHA-256 Certificate Hash',
        ];

        $rows = array_map(function ($r) {
            return [
                $r->facilityId,
                $r->facilityName,
                $r->facilityCode,
                $r->totalWasteReceivedKg,
                $r->totalBiogasProducedM3,
                $r->totalElectricityGeneratedKwh,
                $r->totalTippingFeeRevenueIdr,
                $r->totalAvoidedEmissionsCo2eKg,
                $r->intakeBatchesCount,
                $r->status,
                $r->verificationHash,
            ];
        }, $reports);

        $csv = $reportingService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="biogas-wte-production-report.csv"',
        ]);
    }
}
