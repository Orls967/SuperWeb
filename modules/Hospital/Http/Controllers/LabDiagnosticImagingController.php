<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hospital\Application\Actions\Diagnostics\DispatchOutsourcedLabManifestAction;
use Modules\Hospital\Application\Actions\Diagnostics\ExtractDicomMetadataAction;
use Modules\Hospital\Application\Actions\Diagnostics\ProcessWestgardQcRunAction;
use Modules\Hospital\Application\Actions\Diagnostics\SettleInterBranchClearingAction;
use Modules\Hospital\Application\Actions\Diagnostics\TransferSpecimenCustodyAction;
use Modules\Hospital\Application\Actions\Diagnostics\UploadDicomStudyAction;
use Modules\Hospital\Application\Services\CriticalRangeNotifierService;
use Modules\Hospital\Application\Services\SpecimenCustodyChainService;
use Modules\Hospital\Domain\DTO\DicomStudyUploadDTO;
use Modules\Hospital\Domain\DTO\SpecimenCustodyTransferDTO;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use Modules\Hospital\Domain\Models\LabCatalog;
use Modules\Hospital\Domain\Models\LabCriticalRange;
use Modules\Hospital\Domain\Models\LaboratoryLoincCatalog;
use Modules\Hospital\Domain\Models\LabPartnerContract;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;

/**
 * Controller Layanan Laboratorium Diagnostik & Diagnostic Imaging PACS (Fase 105).
 */
class LabDiagnosticImagingController extends Controller
{
    /**
     * Katalog 10.000 Parameter Lab LOINC & Rentang Kritis (Fase 105.1)
     */
    public function catalogIndex(Request $request): JsonResponse|View
    {
        if ($request->wantsJson()) {
            return $this->catalog($request);
        }

        $catalog = LabCatalog::latest()->take(50)->get();
        $loincCatalog = LaboratoryLoincCatalog::latest()->take(50)->get();
        $criticalRanges = LabCriticalRange::latest()->take(25)->get();

        return view('hospital::laboratory.catalog', compact('catalog', 'loincCatalog', 'criticalRanges'));
    }

    /**
     * Alias spesifikasi teknis: GET /hospital/laboratory/catalog
     */
    public function catalog(Request $request): JsonResponse|View
    {
        if ($request->wantsJson()) {
            $loinc = LaboratoryLoincCatalog::where('is_active', true)
                ->when($request->query('q'), fn ($q, $v) => $q->where('component', 'like', "%{$v}%")->orWhere('loinc_num', $v))
                ->take(50)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $loinc,
            ]);
        }

        return $this->catalogIndex($request);
    }

    /**
     * Pindai Rantai Kustodi Spesimen 3-Hop Hash-Chain (Fase 105.2)
     */
    public function scanSpecimenCustody(Request $request, SpecimenCustodyChainService $custodyService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'specimen_id' => 'nullable|integer',
            'hop_number' => 'required|integer|min:1',
            'from_handler' => 'required|string|max:128',
            'to_handler' => 'required|string|max:128',
            'notes' => 'nullable|string|max:255',
        ]);

        /** @var LabSpecimen|null $specimen */
        $specimen = isset($validated['specimen_id']) ? LabSpecimen::find($validated['specimen_id']) : null;

        if (! $specimen) {
            $patient = Patient::firstOrCreate(
                ['mrn' => 'MRN-LAB-SPECIMEN-01'],
                [
                    'name' => 'Specimen Patient',
                    'date_of_birth' => '1992-05-12',
                    'blood_type' => 'B+',
                    'passport_hash' => hash('sha256', 'PASSPORT:MRN-LAB-SPECIMEN-01'),
                ]
            );

            $specimen = LabSpecimen::firstOrCreate(
                ['specimen_barcode' => 'SPEC-CUSTODY-DEMO'],
                [
                    'patient_id' => $patient->id,
                    'sample_type' => 'BLOOD',
                    'custody_hash' => 'GENESIS',
                    'collected_at' => now(),
                    'status' => 'COLLECTED',
                ]
            );
        }

        $transfer = $custodyService->recordHop(
            $specimen,
            (int) $validated['hop_number'],
            $validated['from_handler'],
            $validated['to_handler'],
            $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $transfer,
            ]);
        }

        return redirect()->route('hospital.laboratory.catalog.index')
            ->with('success', "Specimen custody Hop #{$validated['hop_number']} recorded cryptographically.");
    }

    /**
     * Alias spesifikasi teknis: POST /hospital/laboratory/specimens/custody
     */
    public function transferCustody(Request $request, TransferSpecimenCustodyAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'specimen_id' => 'required|integer|exists:hsp_lab_specimens,id',
            'hop_number' => 'required|integer|min:1',
            'from_handler' => 'required|string|max:128',
            'to_handler' => 'required|string|max:128',
            'transport_temp_celsius' => 'nullable|numeric',
            'notes' => 'nullable|string|max:255',
            'gps_coordinates' => 'nullable|string|max:64',
            'storage_container_barcode' => 'nullable|string|max:64',
        ]);

        $dto = SpecimenCustodyTransferDTO::fromArray($validated);
        $log = $action->execute($dto);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $log,
            ]);
        }

        return redirect()->route('hospital.laboratory.catalog.index')
            ->with('success', "Specimen custody Hop #{$dto->hopNumber} recorded cryptographically.");
    }

    /**
     * Verifikasi Bertingkat & Hold Pathologist Nilai Kritis (Fase 105.3)
     */
    public function verifyLabResult(Request $request, CriticalRangeNotifierService $notifierService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'test_code' => 'required|string|max:64',
            'numeric_value' => 'required|numeric',
        ]);

        $evaluation = $notifierService->evaluateCriticalPanic(
            $validated['test_code'],
            (float) $validated['numeric_value']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $evaluation,
            ]);
        }

        return redirect()->route('hospital.laboratory.catalog.index')
            ->with('panic_evaluation', $evaluation)
            ->with('success', "Lab result evaluated against critical ranges: {$evaluation['protocol']}");
    }

    /**
     * Kliring Antar-Cabang & Scorecard Lab Mitra (Fase 105.4)
     */
    public function outsourcingIndex(Request $request): View
    {
        $contracts = LabPartnerContract::latest()->take(25)->get();
        $clearings = InterBranchClearing::latest()->take(25)->get();

        return view('hospital::laboratory.outsourcing', compact('contracts', 'clearings'));
    }

    /**
     * Booking Modality CT/MRI/USG & Studi DICOM PACS (Fase 105.5)
     */
    public function pacsIndex(Request $request): View
    {
        $studies = ImagingStudy::with(['patient', 'series'])->latest()->take(25)->get();

        return view('hospital::imaging.pacs', compact('studies'));
    }

    /**
     * Upload & Catat Studi Radiologi DICOM PACS (POST /hospital/radiology/dicom/studies)
     */
    public function uploadDicomStudy(Request $request, UploadDicomStudyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:hsp_patients,id',
            'modality' => 'required|string|max:16',
            'body_part' => 'required|string|max:64',
            'turnaround_minutes' => 'required|integer|min:0',
            'fee_idr' => 'required|integer|min:0',
            'radiologist_findings' => 'nullable|string',
            'priority' => 'nullable|string|in:STAT,CITO,ROUTINE',
            'study_instance_uid' => 'nullable|string|max:64',
        ]);

        $dto = DicomStudyUploadDTO::fromArray($validated);
        $study = $action->execute($dto);

        return response()->json([
            'status' => 'success',
            'data' => $study,
        ]);
    }

    /**
     * Tampilkan Metadata WADO-RS JSON untuk Zero-Footprint Viewer (GET /hospital/radiology/dicom/studies/{id}/metadata)
     */
    public function viewDicomMetadata(int $id, ExtractDicomMetadataAction $action): JsonResponse
    {
        $metadata = $action->execute($id);

        return response()->json([
            'status' => 'success',
            'data' => $metadata,
        ]);
    }

    /**
     * Settlement Kliring Finansial Antar-Cabang (POST /hospital/laboratory/clearings/settle)
     */
    public function settleInterBranchClearing(Request $request, SettleInterBranchClearingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'clearing_id' => 'required|integer|exists:hsp_inter_branch_clearings,id',
        ]);

        $clearing = $action->execute((int) $validated['clearing_id']);

        return response()->json([
            'status' => 'success',
            'data' => $clearing,
        ]);
    }

    /**
     * Evaluasi Kendali Mutu Alat Analis Westgard Multirules (POST /hospital/laboratory/qc/evaluate)
     */
    public function evaluateQc(Request $request, ProcessWestgardQcRunAction $action): JsonResponse
    {
        $validated = $request->validate([
            'analyzer_code' => 'required|string|max:32',
            'lot_number' => 'required|string|max:64',
            'control_level' => 'required|string|in:LEVEL_1,LEVEL_2,LEVEL_3',
            'test_code' => 'required|string|max:32',
            'target_mean' => 'required|numeric',
            'target_sd' => 'required|numeric|gt:0',
            'measured_value' => 'required|numeric',
            'recent_z_scores' => 'nullable|array',
            'recent_z_scores.*' => 'numeric',
        ]);

        $dto = $action->execute(
            analyzerCode: $validated['analyzer_code'],
            lotNumber: $validated['lot_number'],
            controlLevel: $validated['control_level'],
            testCode: $validated['test_code'],
            targetMean: (float) $validated['target_mean'],
            targetSd: (float) $validated['target_sd'],
            measuredValue: (float) $validated['measured_value'],
            recentZScores: array_map('floatval', $validated['recent_z_scores'] ?? []),
            technologistId: $request->user()?->id
        );

        return response()->json([
            'status' => 'success',
            'data' => $dto,
        ]);
    }

    /**
     * Menerbitkan Manifest Rujukan Spesimen ke Lab Rekanan (POST /hospital/laboratory/outsourcing/manifests)
     */
    public function dispatchManifest(Request $request, DispatchOutsourcedLabManifestAction $action): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|integer|exists:hsp_lab_partner_contracts,id',
            'specimen_ids' => 'required|array|min:1',
            'specimen_ids.*' => 'integer|exists:hsp_lab_specimens,id',
            'courier_batch_barcode' => 'required|string|max:64',
            'departure_temp_celsius' => 'nullable|numeric',
        ]);

        $manifest = $action->execute(
            contractId: (int) $validated['contract_id'],
            specimenIds: array_map('intval', $validated['specimen_ids']),
            courierBatchBarcode: $validated['courier_batch_barcode'],
            departureTempCelsius: (float) ($validated['departure_temp_celsius'] ?? 4.0)
        );

        return response()->json([
            'status' => 'success',
            'data' => $manifest,
        ]);
    }
}
