<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hospital\Application\Actions\AdjudicateBpjsClaimAction;
use Modules\Hospital\Application\Actions\CreateOutpatientPrescriptionAction;
use Modules\Hospital\Application\Actions\DispensePrescriptionFefoAction;
use Modules\Hospital\Application\Actions\ExaminePatientAndDiagnoseAction;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\Actions\RecordVitalSignsAction;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;

class OutpatientController extends Controller
{
    public function registrationIndex(): View
    {
        $polyclinics = Polyclinic::where('is_active', true)->with('doctors.user')->get();

        return view('hospital::outpatient.registration', compact('polyclinics'));
    }

    public function registrationStore(Request $request, RegisterPatientAndIssueQueueAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'min:16'],
            'full_name' => ['required', 'string', 'max:128'],
            'date_of_birth' => ['required', 'date'],
            'blood_type' => ['required', 'string', 'max:8'],
            'polyclinic_id' => ['required', 'integer', 'exists:hsp_polyclinics,id'],
            'doctor_id' => ['required', 'integer', 'exists:hsp_doctors,id'],
            'payment_method' => ['required', 'in:CASH,BPJS'],
            'bpjs_card_number' => ['nullable', 'string', 'max:32'],
        ]);

        $dto = new RegisterPatientDTO(
            nik: (string) $validated['nik'],
            fullName: (string) $validated['full_name'],
            dateOfBirth: Carbon::parse($validated['date_of_birth']),
            bloodType: (string) $validated['blood_type'],
            polyclinicId: (int) $validated['polyclinic_id'],
            doctorId: (int) $validated['doctor_id'],
            paymentMethod: (string) $validated['payment_method'],
            bpjsCardNumber: $validated['bpjs_card_number'] ?? null,
        );

        $result = $action->execute($dto, $request->user());

        return redirect()->route('hospital.outpatient.queue.index')
            ->with('success', "Pasien {$dto->fullName} berhasil terdaftar dengan antrian #{$result['queue']->queue_number}");
    }

    public function queueIndex(): View
    {
        $queues = QueueTicket::whereDate('queue_date', now()->toDateString())
            ->with(['polyclinic', 'doctor.user', 'encounter.patient'])
            ->orderBy('queue_number', 'asc')
            ->get();

        return view('hospital::outpatient.queue', compact('queues'));
    }

    public function queueCall(QueueTicket $queue): RedirectResponse
    {
        $queue->update(['status' => 'CALLED']);
        if ($queue->encounter) {
            $queue->encounter->update(['status' => 'CALLED']);
        }

        return redirect()->back()->with('success', "Antrian #{$queue->queue_number} berhasil dipanggil.");
    }

    public function triage(OutpatientEncounter $encounter, Request $request, RecordVitalSignsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'systolic' => ['required', 'integer', 'min:50', 'max:250'],
            'diastolic' => ['required', 'integer', 'min:30', 'max:150'],
            'pulse' => ['required', 'integer', 'min:30', 'max:220'],
            'respiratory' => ['required', 'integer', 'min:8', 'max:60'],
            'temperature' => ['required', 'numeric', 'min:30', 'max:45'],
        ]);

        $dto = new VitalSignsDTO(
            systolic: (int) $validated['systolic'],
            diastolic: (int) $validated['diastolic'],
            pulse: (int) $validated['pulse'],
            respiratory: (int) $validated['respiratory'],
            temperature: (float) $validated['temperature'],
        );

        $action->execute($encounter, $dto, $request->user());

        return redirect()->back()->with('success', 'Tanda-tanda vital berhasil disimpan.');
    }

    public function examine(OutpatientEncounter $encounter, Request $request, ExaminePatientAndDiagnoseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'doctor_notes' => ['required', 'string'],
            'icd10_code' => ['required', 'string'],
        ]);

        $dto = new DoctorExaminationDTO(
            doctorNotes: (string) $validated['doctor_notes'],
            icd10DiagnosisCode: (string) $validated['icd10_code'],
        );

        $action->execute($encounter, $dto, $request->user());

        return redirect()->back()->with('success', 'Pemeriksaan dokter berhasil disimpan.');
    }

    public function prescribe(OutpatientEncounter $encounter, Request $request, CreateOutpatientPrescriptionAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'drug_code' => ['required', 'string'],
            'drug_name' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'dosage_instructions' => ['required', 'string'],
        ]);

        $dto = new PrescriptionItemDTO(
            drugCode: (string) $validated['drug_code'],
            drugName: (string) $validated['drug_name'],
            quantity: (int) $validated['quantity'],
            dosageInstructions: (string) $validated['dosage_instructions'],
        );

        $action->execute($encounter, $dto, $request->user());

        return redirect()->back()->with('success', 'Resep obat berhasil diterbitkan.');
    }

    public function pharmacyIndex(): View
    {
        $prescriptions = OutpatientPrescription::where('status', 'PRESCRIBED')
            ->with(['encounter.patient', 'doctor.user'])
            ->get();

        return view('hospital::outpatient.pharmacy', compact('prescriptions'));
    }

    public function dispense(OutpatientPrescription $prescription, Request $request, DispensePrescriptionFefoAction $action): RedirectResponse
    {
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta']);
        $action->execute($prescription, $branch->id, $request->user());

        return redirect()->back()->with('success', 'Obat berhasil diserahkan dan stok dipotong.');
    }

    public function cashierIndex(): View
    {
        $invoices = OutpatientInvoice::where('status', 'PENDING')
            ->with(['encounter.patient'])
            ->get();

        return view('hospital::outpatient.cashier', compact('invoices'));
    }

    public function cashierPay(OutpatientInvoice $invoice, Request $request, ProcessCashPaymentAction $action): JsonResponse|RedirectResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (empty($idempotencyKey)) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
                'errors' => [
                    'idempotency_key' => ['Idempotency-Key header is required.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'cash_received_idr' => ['required', 'integer', 'min:0'],
        ]);

        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: (int) $validated['cash_received_idr'],
            idempotencyKey: (string) $idempotencyKey,
            cashierUserId: (int) auth()->id(),
        );

        $payment = $action->execute($dto);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'payment' => $payment,
            ]);
        }

        return redirect()->back()->with('success', 'Pembayaran kasir berhasil diselesaikan.');
    }

    public function claimBpjs(OutpatientInvoice $invoice, Request $request, SubmitBpjsClaimAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'ina_cbg_code' => ['required', 'string'],
            'package_tariff_idr' => ['required', 'integer', 'min:0'],
        ]);

        $action->execute(
            $invoice,
            (string) $validated['ina_cbg_code'],
            (int) $validated['package_tariff_idr']
        );

        return redirect()->back()->with('success', 'Klaim BPJS berhasil diajukan.');
    }

    public function adjudicateBpjs(BpjsClaim $claim, Request $request, AdjudicateBpjsClaimAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'approved' => ['required'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $isApproved = filter_var($validated['approved'], FILTER_VALIDATE_BOOLEAN);
        $action->execute($claim, $isApproved, $validated['rejection_reason'] ?? null);

        return redirect()->back()->with('success', 'Adjudikasi klaim berhasil diproses.');
    }

    public function dailyReport(Request $request, GenerateDailyPolyclinicReportAction $action): View
    {
        $date = (string) $request->input('date', now()->toDateString());
        $report = $action->execute($date);

        return view('hospital::outpatient.daily_report', compact('report', 'date'));
    }

    public function portalIndex(): View
    {
        $user = auth()->user();
        $encounters = OutpatientEncounter::whereHas('patient', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['polyclinic', 'doctor.user', 'invoice'])->get();

        return view('hospital::outpatient.portal', compact('encounters'));
    }

    public function encounterShow(OutpatientEncounter $encounter): View
    {
        $user = auth()->user();
        if ($user->role === 'patient' && $encounter->patient->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        return view('hospital::outpatient.encounter_show', compact('encounter'));
    }

    public function invoiceShow(OutpatientInvoice $invoice): View
    {
        $user = auth()->user();
        if ($user->role === 'patient' && $invoice->encounter->patient->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        return view('hospital::outpatient.invoice_show', compact('invoice'));
    }
}
