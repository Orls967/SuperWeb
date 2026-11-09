<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hospital\Application\Actions\Care\AccrueDailyBedRevenueAction;
use Modules\Hospital\Application\Actions\Care\AcknowledgeCodeBlueAction;
use Modules\Hospital\Application\Actions\Care\AdmitPatientAction;
use Modules\Hospital\Application\Actions\Care\DischargePatientAction;
use Modules\Hospital\Application\Actions\Care\EvaluateVitalsTelemetryAction;
use Modules\Hospital\Application\Actions\Care\PlanDischargeAction;
use Modules\Hospital\Application\Actions\Care\RegisterPatientAction;
use Modules\Hospital\Application\Actions\Care\ResolveCodeBlueAction;
use Modules\Hospital\Application\Actions\Care\ScheduleClinicalOrderAction;
use Modules\Hospital\Application\Actions\Care\TransferBedAction;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyIndicators;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\VitalsTelemetry;
use Modules\Hospital\Domain\Services\PatientPassportService;

class HospitalCareController extends Controller
{
    /**
     * Papan kontrol tempat tidur real-time (Fase 87.3).
     */
    public function bedsIndex(Request $request): View
    {
        $beds = HospitalBed::orderBy('bed_code')->paginate(25);
        $admissionsQueue = Admission::where('status', 'requested')
            ->with('patient')
            ->orderBy('id')
            ->get();

        $occupancyCount = HospitalBed::where('status', 'OCCUPIED')->count();
        $totalBeds = HospitalBed::count();
        $borPercentage = $totalBeds > 0 ? round(($occupancyCount / $totalBeds) * 100, 2) : 0.0;

        return view('hospital::care.beds', compact('beds', 'admissionsQueue', 'occupancyCount', 'totalBeds', 'borPercentage'));
    }

    /**
     * Papan antrean dan triase gawat darurat (ER Board) (Fase 87.4).
     */
    public function erIndex(Request $request): View
    {
        $activeTriage = VitalsTelemetry::with('encounter.patient')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $activeCodeBlue = CodeBlueEvent::whereNotIn('status', ['resolved', 'closed'])
            ->with(['admission.patient', 'bed'])
            ->orderByDesc('id')
            ->get();

        return view('hospital::care.er', compact('activeTriage', 'activeCodeBlue'));
    }

    /**
     * Papan tugas perawat mobile 375 px (Nurse Board) (Fase 87.4 / T-6b).
     */
    public function nursingIndex(Request $request): View
    {
        $orders = HospitalOrder::whereIn('status', ['SCHEDULED', 'IN_PROGRESS', 'DELAYED'])
            ->with('encounter.patient')
            ->orderBy('scheduled_at')
            ->paginate(15);

        $activeAlerts = HospitalOrder::where('status', 'DELAYED')->count();

        return view('hospital::care.nursing', compact('orders', 'activeAlerts'));
    }

    /**
     * Monitor critical care dan alarm Code Blue (Fase 87.5).
     */
    public function criticalCareIndex(Request $request): View
    {
        $events = CodeBlueEvent::with(['admission.patient', 'bed'])
            ->orderByDesc('id')
            ->paginate(15);

        $activeCount = CodeBlueEvent::whereNotIn('status', ['resolved', 'closed'])->count();

        return view('hospital::care.critical_care', compact('events', 'activeCount'));
    }

    /**
     * Tampilan paspor kesehatan pasien terenkripsi (Fase 87.2).
     */
    public function patientPassport(Request $request, int $patientId, PatientPassportService $passportService): JsonResponse|View
    {
        $patient = Patient::with('passportEvents')->findOrFail($patientId);

        // IDOR Check: Pasien hanya boleh melihat paspor miliknya sendiri (kecuali dokter / admin / registration)
        $user = $request->user();
        if ($user && in_array($user->role, ['patient'], true)) {
            abort(403, 'Akses rekam medis pasien lain dilarang.');
        }

        $isChainValid = $passportService->verifyChain($patient);

        if ($request->wantsJson()) {
            return response()->json([
                'mrn' => $patient->mrn,
                'blood_type' => $patient->blood_type,
                'passport_hash' => $patient->passport_hash,
                'is_chain_valid' => $isChainValid,
                'events' => $patient->passportEvents,
            ]);
        }

        return view('hospital::care.passport', compact('patient', 'isChainValid'));
    }

    /**
     * Dashboard efisiensi tempat tidur Barber-Johnson (Fase 87.6).
     */
    public function efficiencyIndex(Request $request, BedEfficiencyIndicators $calculator): View
    {
        $totalBeds = max(1, HospitalBed::count());
        $inpatientDays = Admission::whereIn('status', ['admitted', 'transferred', 'discharge_planned', 'discharged'])->count() * 4;
        $inpatientDays = max(100, $inpatientDays);
        $discharges = max(10, Admission::where('status', 'discharged')->count());

        $bjResult = $calculator->calculate($totalBeds, 30, $inpatientDays, $discharges);

        return view('hospital::care.efficiency', compact('bjResult', 'totalBeds', 'inpatientDays', 'discharges'));
    }

    /**
     * Eksekusi admisi rawat inap via saga (Fase 87.3).
     */
    public function storeAdmission(Request $request, AdmitPatientAction $action): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (empty($idempotencyKey)) {
            return response()->json([
                'error' => 'Header Idempotency-Key wajib disertakan pada transaksi admisi.',
            ], 422);
        }

        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:hsp_patients,id',
            'bed_id' => 'nullable|integer|exists:hsp_beds,id',
            'required_class' => 'required|string',
            'deposit_idr' => 'required|integer|min:0',
            'priority' => 'nullable|integer',
        ]);

        try {
            $result = $action->execute(array_merge($validated, [
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $request->user()?->id,
            ]));

            return response()->json($result);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Pengakuan bed-day harian admisi (Fase 87.7).
     */
    public function accrueBedDay(Request $request, int $admissionId, AccrueDailyBedRevenueAction $action): JsonResponse
    {
        $serviceDate = (string) ($request->input('service_date', date('Y-m-d')));
        $idempotencyKey = (string) ($request->header('Idempotency-Key') ?? "hsp:bedday:{$admissionId}:{$serviceDate}");

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1',
            'service_date' => 'nullable|string',
        ]);

        $days = (int) ($validated['days'] ?? 1);
        $action->execute($admissionId, $days, $idempotencyKey);

        return response()->json([
            'success' => true,
            'admission_id' => $admissionId,
            'days_accrued' => $days,
            'message' => 'Bed-day berhasil diakui ke dalam buku besar.',
        ]);
    }

    /**
     * Pemulangan pasien (Discharge) via saga (Fase 87.3).
     */
    public function discharge(Request $request, int $admissionId, DischargePatientAction $action): JsonResponse
    {
        $idempotencyKey = (string) ($request->header('Idempotency-Key') ?? "hsp:discharge:{$admissionId}:final");

        $validated = $request->validate([
            'total_billed_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->execute([
            'admission_id' => $admissionId,
            'total_billed_idr' => (int) ($validated['total_billed_idr'] ?? 0),
            'idempotency_key' => $idempotencyKey,
            'actor_id' => $request->user()?->id,
        ]);

        return response()->json($result);
    }

    /**
     * Acknowledge Code Blue (Fase 87.5).
     */
    public function ackCodeBlue(Request $request, int $eventId, AcknowledgeCodeBlueAction $action): JsonResponse
    {
        $role = $request->user()?->role ?? 'nurse';
        $event = $action->execute($eventId, $role, $request->user()?->id);

        return response()->json([
            'success' => true,
            'event' => $event,
        ]);
    }

    /**
     * Resolve Code Blue (Fase 87.5).
     */
    public function resolveCodeBlue(Request $request, int $eventId, ResolveCodeBlueAction $action): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string',
        ]);

        $event = $action->execute($eventId, $validated['notes'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'event' => $event,
        ]);
    }
}
