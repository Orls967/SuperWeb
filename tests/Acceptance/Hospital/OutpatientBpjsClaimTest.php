<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\AdjudicateBpjsClaimAction;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientBpjsClaimTest extends HospitalAcceptanceHelper
{
    protected function createBpjsEncounterFixture(): OutpatientEncounter
    {
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Penyakit Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $patient = Patient::create(['mrn' => 'MRN-'.uniqid(), 'name' => 'Pasien BPJS', 'date_of_birth' => '1987-03-15', 'blood_type' => 'B', 'passport_hash' => hash('sha256', 'p')]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'BPJS',
            'bpjs_card_number' => '0001234567890',
            'status' => 'DISPENSED',
            'encrypted_icd10_code' => 'K30', // Dispepsia
        ]);
    }

    public function test_pendaftaran_pasien_jalur_bpjs_memvalidasi_nomor_kartu_jkn(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        // Pendaftaran jalur BPJS tanpa nomor kartu JKN harus ditolak
        $dtoWithoutCard = new RegisterPatientDTO(
            nik: '3171000000000099',
            fullName: 'BPJS Tanpa Kartu',
            dateOfBirth: Carbon::parse('1990-01-01'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doc->id,
            paymentMethod: 'BPJS',
            bpjsCardNumber: null, // Kosong
        );

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($dtoWithoutCard, $regStaff);
    }

    public function test_tagihan_pasien_bpjs_menghasilkan_klaim_tarif_paket_inacbg_ke_piutang_bpjs(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-01',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000,
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        // Paket INA-CBG untuk Rawat Jalan K30 (Dispepsia Ringan): Tarif Paket Rp 180.000
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        $this->assertInstanceOf(BpjsClaim::class, $claim);
        $this->assertSame(180000, $claim->ina_cbg_tariff_idr);
        $this->assertSame('SUBMITTED', $claim->status);
        $this->assertSame('CLAIMED', $invoice->fresh()->status);
        $this->assertSame('CLAIMED', $encounter->fresh()->status);

        // Verifikasi mutasi debit pada Piutang BPJS sebesar nilai paket INA-CBG Rp 180.000
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 180000, 'DEBIT');
    }

    public function test_selisih_tarif_rs_vs_paket_inacbg_diakui_pada_akun_selisih_secara_seimbang(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-02',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000, // Tarif RS = 170.000
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        // Paket INA-CBG Rp 180.000 -> Surplus selisih Rp 10.000
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        $this->assertSame(10000, $claim->variance_amount_idr);

        // Kredit pendapatan selisih tarif Rp 10.000
        $this->assertLedgerPosted('hsp:bpjs_claim_variance:IDR', 10000, 'CREDIT');

        // Total debit dan total kredit posting klaim harus seimbang
        $tx = DB::table('bank_ledger_transactions')
            ->where('reference_type', 'HSP_BPJS_CLAIM')
            ->where('reference_id', (string) $claim->id)
            ->firstOrFail();

        $sum = DB::table('bank_ledger_entries')->where('transaction_id', $tx->id)->sum('amount');
        $this->assertEquals(0, (int) $sum);
    }

    public function test_pasien_jkn_tidak_dikenakan_iur_biaya_tambahan_pada_rawat_jalan(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-03',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000,
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        // Tagihan pasien harus 0 (ditanggung 100% oleh penjamin JKN)
        $this->assertSame(0, $claim->patient_copay_amount_idr ?? 0);
    }

    public function test_klaim_yang_disetujui_bpjs_mempertahankan_status_approved_dan_saldo_piutang(): void
    {
        $encounter = $this->createBpjsEncounterFixture();
        $invoice = OutpatientInvoice::create(['invoice_code' => 'INV-BPJS-04', 'encounter_id' => $encounter->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 0, 'total_amount_idr' => 150000, 'payment_method' => 'BPJS', 'status' => 'PENDING']);
        $claim = app(SubmitBpjsClaimAction::class)->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 150000);

        $adjAction = app(AdjudicateBpjsClaimAction::class);
        $updatedClaim = $adjAction->execute($claim, approved: true);

        $this->assertSame('APPROVED', $updatedClaim->status);
        $this->assertSame('SETTLED', $encounter->fresh()->status);
        // Piutang BPJS tetap ada dan tidak dijurnal balik
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 150000, 'DEBIT');
    }

    public function test_klaim_yang_ditolak_bpjs_memicu_jurnal_balik_piutang_bpjs(): void
    {
        $encounter = $this->createBpjsEncounterFixture();
        $invoice = OutpatientInvoice::create(['invoice_code' => 'INV-BPJS-05', 'encounter_id' => $encounter->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 0, 'total_amount_idr' => 150000, 'payment_method' => 'BPJS', 'status' => 'PENDING']);
        $claim = app(SubmitBpjsClaimAction::class)->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 150000);

        $adjAction = app(AdjudicateBpjsClaimAction::class);
        $rejectedClaim = $adjAction->execute($claim, approved: false, rejectionReason: 'Kelengkapan berkas rekam medis tidak memenuhi syarat koding');

        $this->assertSame('REJECTED', $rejectedClaim->status);
        $this->assertSame('Kelengkapan berkas rekam medis tidak memenuhi syarat koding', $rejectedClaim->rejection_reason);

        // Verifikasi jurnal pembalik: KREDIT Piutang BPJS Rp 150.000 dan DEBIT Beban Klaim Ditolak Rp 150.000
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 150000, 'CREDIT');
        $this->assertLedgerPosted('hsp:bpjs_rejected_claim:IDR', 150000, 'DEBIT');
    }
}
