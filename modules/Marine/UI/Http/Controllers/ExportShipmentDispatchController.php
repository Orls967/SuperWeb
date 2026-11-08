<?php

declare(strict_types=1);

namespace Modules\Marine\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marine\Application\Actions\ArbitrateAssayDisputeAction;
use Modules\Marine\Application\Actions\DispatchExportReeferAction;
use Modules\Marine\Application\Actions\IssueExportHealthCertificateAction;
use Modules\Marine\Application\Actions\RetireBlueCarbonInsettingAction;
use Modules\Marine\Application\Services\ExportDocumentationService;
use Modules\Marine\Application\Services\HarvestProcessingColdChainService;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;

class ExportShipmentDispatchController extends Controller
{
    protected DispatchExportReeferAction $dispatchAction;

    protected IssueExportHealthCertificateAction $hcAction;

    protected ArbitrateAssayDisputeAction $arbitrateAction;

    protected RetireBlueCarbonInsettingAction $insettingAction;

    protected HarvestProcessingColdChainService $coldChainService;

    protected ExportDocumentationService $docService;

    public function __construct(
        DispatchExportReeferAction $dispatchAction,
        IssueExportHealthCertificateAction $hcAction,
        ArbitrateAssayDisputeAction $arbitrateAction,
        RetireBlueCarbonInsettingAction $insettingAction,
        HarvestProcessingColdChainService $coldChainService,
        ExportDocumentationService $docService
    ) {
        $this->dispatchAction = $dispatchAction;
        $this->hcAction = $hcAction;
        $this->arbitrateAction = $arbitrateAction;
        $this->insettingAction = $insettingAction;
        $this->coldChainService = $coldChainService;
        $this->docService = $docService;
    }

    /**
     * Terbitkan sertifikat fitosanitari ekspor resmi.
     */
    public function issueHealthCertificate(Request $request, int $exportLotId): JsonResponse
    {
        $inspector = (string) ($request->input('inspector_name', 'Inspector Official KKP'));
        $result = $this->hcAction->execute($exportLotId, $inspector);

        return response()->json([
            'success' => true,
            'message' => 'Health certificate diterbitkan.',
            'certificate' => $result,
        ]);
    }

    /**
     * Terbitkan dokumen COO (Certificate of Origin).
     */
    public function issueCertificateOfOrigin(Request $request, int $exportLotId): JsonResponse
    {
        $formType = (string) ($request->input('form_type', 'FORM_D'));
        $country = (string) ($request->input('consignee_country', 'US'));

        $coo = $this->docService->generateCertificateOfOrigin($exportLotId, $formType, $country);

        return response()->json([
            'success' => true,
            'message' => 'Certificate of Origin diterbitkan.',
            'certificate_of_origin' => $coo,
        ]);
    }

    /**
     * Catat telemetri suhu reefer container cold chain.
     */
    public function recordColdChainTelemetry(Request $request, int $shipmentId): JsonResponse
    {
        $validated = $request->validate([
            'temperature_celsius' => 'required|numeric',
            'humidity_percentage' => 'required|numeric',
            'gps_latitude' => 'required|string',
            'gps_longitude' => 'required|string',
        ]);

        $log = $this->coldChainService->recordColdChainTelemetry(
            $shipmentId,
            (float) $validated['temperature_celsius'],
            (float) $validated['humidity_percentage'],
            (string) $validated['gps_latitude'],
            (string) $validated['gps_longitude']
        );

        return response()->json([
            'success' => true,
            'message' => 'Telemetri cold chain dicatat.',
            'telemetry' => $log,
        ], 201);
    }

    /**
     * Dispatch kontainer reefer ke laut / kapal ekspor.
     */
    public function dispatchShipment(int $shipmentId): JsonResponse
    {
        $shipment = $this->dispatchAction->execute($shipmentId);

        return response()->json([
            'success' => true,
            'message' => 'Kontainer reefer resmi diberangkatkan.',
            'shipment' => $shipment,
        ]);
    }

    /**
     * Eksekusi arbitrase sengketa hasil pengujian mutu.
     */
    public function arbitrateDispute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dispute_reference' => 'required|string',
            'export_lot_id' => 'required|integer',
            'escrow_settlement_id' => 'required|integer',
            'destination_market' => 'required|string',
            'lab_results' => 'required|array',
        ]);

        $lot = AquacultureExportLot::findOrFail((int) $validated['export_lot_id']);
        $escrow = AquacultureEscrowSettlement::findOrFail((int) $validated['escrow_settlement_id']);

        $result = $this->arbitrateAction->execute(
            (string) $validated['dispute_reference'],
            $lot,
            $escrow,
            (string) $validated['destination_market'],
            (array) $validated['lab_results']
        );

        return response()->json([
            'success' => true,
            'arbitration_verdict' => $result,
        ]);
    }
}
