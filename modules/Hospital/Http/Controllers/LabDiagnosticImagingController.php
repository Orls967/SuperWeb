<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hospital\Application\Services\CriticalRangeNotifierService;
use Modules\Hospital\Application\Services\SpecimenCustodyChainService;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use Modules\Hospital\Domain\Models\LabCatalog;
use Modules\Hospital\Domain\Models\LabCriticalRange;
use Modules\Hospital\Domain\Models\LabPartnerContract;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;

class LabDiagnosticImagingController extends Controller
{
    /**
     * Katalog 10.000 Parameter Lab LOINC & Rentang Kritis (Fase 105.1)
     */
    public function catalogIndex(Request $request): View
    {
        $catalog = LabCatalog::latest()->take(50)->get();
        $criticalRanges = LabCriticalRange::latest()->take(25)->get();

        return view('hospital::laboratory.catalog', compact('catalog', 'criticalRanges'));
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
}
