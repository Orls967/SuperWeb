<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hospital\Application\Services\PharmacyMultiBranchRoutingService;
use Modules\Hospital\Application\Services\PrescriptionSafetyEvaluator;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Prescription;
use Modules\Hospital\Domain\Models\TeleConsult;

class TelemedicinePharmacyController extends Controller
{
    /**
     * Dashboard Telemedicine, Triase & E-Resep Digital (Fase 104.1 & 104.2)
     */
    public function telemedicineIndex(Request $request): View
    {
        $teleConsults = TeleConsult::with('patient')->latest()->take(25)->get();
        $prescriptions = Prescription::with('patient')->latest()->take(25)->get();

        return view('hospital::telemedicine.index', compact('teleConsults', 'prescriptions'));
    }

    /**
     * Evaluasi Interaksi Obat & Alergi terhadap Human Passport (Fase 104.3)
     */
    public function evaluatePrescription(Request $request, PrescriptionSafetyEvaluator $evaluator): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => 'nullable|integer',
            'drug_code' => 'required|string|max:64',
            'dosage_mg' => 'required|integer|min:1',
            'crcl' => 'nullable|integer|min:0',
        ]);

        /** @var Patient $patient */
        $patient = isset($validated['patient_id'])
            ? (Patient::find($validated['patient_id']) ?? Patient::firstOrCreate(
                ['mrn' => 'MRN-EVAL-001'],
                [
                    'name' => 'Demo Passport Patient',
                    'date_of_birth' => '1990-01-01',
                    'blood_type' => 'O+',
                    'encrypted_allergies' => json_encode(['PENICILLIN']),
                    'passport_hash' => hash('sha256', 'PASSPORT:MRN-EVAL-001'),
                ]
            ))
            : Patient::firstOrCreate(
                ['mrn' => 'MRN-EVAL-001'],
                [
                    'name' => 'Demo Passport Patient',
                    'date_of_birth' => '1990-01-01',
                    'blood_type' => 'O+',
                    'encrypted_allergies' => json_encode(['PENICILLIN']),
                    'passport_hash' => hash('sha256', 'PASSPORT:MRN-EVAL-001'),
                ]
            );

        $evaluation = $evaluator->evaluateSafety(
            $patient,
            $validated['drug_code'],
            (int) $validated['dosage_mg'],
            isset($validated['crcl']) ? (int) $validated['crcl'] : null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $evaluation,
            ]);
        }

        return redirect()->route('hospital.telemedicine.index')
            ->with('evaluation', $evaluation)
            ->with('success', 'Prescription safety evaluated against patient human passport.');
    }

    /**
     * Manajemen Langganan Obat Kronis & FEFO Lot (Fase 104.4)
     */
    public function subscriptionsIndex(Request $request): View
    {
        $subscriptions = ChronicSubscription::with(['patient', 'branch'])->latest()->take(25)->get();
        $lots = PharmacyBranchLot::with('branch')->latest()->take(25)->get();

        return view('hospital::epharmacy.subscriptions', compact('subscriptions', 'lots'));
    }

    /**
     * Multi-Branch Pharmacy Routing & Inpatient Referral (Fase 104.5)
     */
    public function dispatchRouting(Request $request, PharmacyMultiBranchRoutingService $routingService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'city' => 'required|string|max:100',
            'drug_code' => 'required|string|max:64',
            'quantity' => 'required|integer|min:1',
        ]);

        $bestBranch = $routingService->findBestBranch(
            $validated['city'],
            $validated['drug_code'],
            (int) $validated['quantity']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'best_branch' => $bestBranch,
            ]);
        }

        $branchName = $bestBranch ? $bestBranch->name : 'No eligible branch found';

        return redirect()->route('hospital.epharmacy.subscriptions.index')
            ->with('success', "Pharmacy dispatch routed to: {$branchName}");
    }
}
