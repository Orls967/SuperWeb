<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketplaceService;
use Modules\Egy\Domain\Models\CbamDeclaration;

final class EgyCbamController extends Controller
{
    public function __construct(
        private readonly CarbonTradingAndEsgMarketplaceService $marketplaceService,
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->marketplaceService->getCbamDeclarationsOverview();

        return view('egy::cbam.index', $overview);
    }

    public function export(): Response
    {
        $declarations = CbamDeclaration::all();
        $headers = [
            'Declaration Number',
            'Contract Number',
            'Container Number',
            'HS Code',
            'Destination',
            'Exporter Party ID',
            'Product (kg)',
            'Embedded Emissions (kg CO2e)',
            'Net Payable (kg CO2e)',
            'Liability (EUR Cent)',
            'Liability (IDR)',
            'Status',
            'SHA-256 Hash',
        ];

        $rows = array_map(function ($d) {
            return [
                $d->declaration_number,
                $d->export_contract_number,
                $d->container_number,
                $d->hs_code,
                $d->country_destination,
                $d->exporter_party_id,
                $d->product_tonnage_kg,
                $d->gross_embedded_emissions_kg_co2e,
                $d->net_payable_emissions_kg_co2e,
                $d->total_cbam_liability_eur_minor,
                $d->total_cbam_liability_idr,
                $d->status,
                $d->certificate_hash,
            ];
        }, iterator_to_array($declarations));

        $csv = $this->marketplaceService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cbam-declarations-audit.csv"',
        ]);
    }
}
