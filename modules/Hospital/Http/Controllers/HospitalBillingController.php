<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Hospital\Application\Actions\Billing\AddFolioItemAction;
use Modules\Hospital\Application\Actions\Billing\CreateBillingEpisodeAction;
use Modules\Hospital\Application\Actions\Billing\ProcessCoBWaterfallAction;
use Modules\Hospital\Application\Actions\Operations\HandoverMedicalWasteAction;
use Modules\Hospital\Application\Actions\Operations\LogMedicalWasteAction;
use Modules\Hospital\Application\Actions\Operations\RecordColdChainReadingAction;
use Modules\Hospital\Application\Sagas\HospitalBillingReconciliationSaga;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Services\ClaimAgingAnalysisService;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;
use Modules\Hospital\Http\Requests\AddFolioItemRequest;
use Modules\Hospital\Http\Requests\CreateBillingEpisodeRequest;
use Modules\Hospital\Http\Requests\HandoverWasteRequest;
use Modules\Hospital\Http\Requests\LogMedicalWasteRequest;
use Modules\Hospital\Http\Requests\ProcessWaterfallRequest;
use Modules\Hospital\Http\Requests\RecordColdChainRequest;

final class HospitalBillingController extends Controller
{
    public function createEpisode(
        CreateBillingEpisodeRequest $request,
        CreateBillingEpisodeAction $action
    ): JsonResponse {
        $episode = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $episode,
        ], 201);
    }

    public function addFolioItem(
        AddFolioItemRequest $request,
        AddFolioItemAction $action
    ): JsonResponse {
        $item = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $item,
        ], 201);
    }

    public function reconcileAndSettle(
        ProcessWaterfallRequest $request,
        HospitalBillingReconciliationSaga $saga
    ): JsonResponse {
        $breakdown = $saga->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $breakdown->toArray(),
        ]);
    }

    public function recordColdChain(
        RecordColdChainRequest $request,
        RecordColdChainReadingAction $action
    ): JsonResponse {
        $log = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $log->toArray(),
        ], 201);
    }

    public function logWaste(
        LogMedicalWasteRequest $request,
        LogMedicalWasteAction $action
    ): JsonResponse {
        $record = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $record->toArray(),
        ], 201);
    }

    public function handoverWaste(
        HandoverWasteRequest $request,
        HandoverMedicalWasteAction $action
    ): JsonResponse {
        $record = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $record->toArray(),
        ]);
    }

    public function verifyWasteCustody(
        int $manifestId,
        MedicalWasteManifestService $service
    ): JsonResponse {
        $manifest = MedicalWasteManifest::findOrFail($manifestId);
        $isValid = $service->verifyCustodyChainIntegrity($manifest);

        return response()->json([
            'status' => 'success',
            'manifest_number' => $manifest->manifest_number,
            'is_chain_valid' => $isValid,
            'current_hash' => $manifest->custody_hash,
        ]);
    }

    public function getAgingReport(
        ClaimAgingAnalysisService $agingService
    ): JsonResponse {
        $summary = $agingService->generateAgingSummary();

        return response()->json([
            'status' => 'success',
            'data' => $summary->toArray(),
        ]);
    }
}
