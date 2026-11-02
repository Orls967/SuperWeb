<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Modules\Hospital\Application\Actions\CreateOutpatientPrescriptionAction;
use Modules\Hospital\Application\Actions\DispensePrescriptionFefoAction;
use Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientDispensing;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientDocumentNumberingTest extends HospitalAcceptanceHelper
{
    public function test_format_persis_seluruh_dokumen_rawat_jalan(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_DOC_NUM'], ['name' => 'Poli Penomoran', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-NUM-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);
        $cashier = $this->createHospitalUser('cashier');

        // 1. MRN & ENC
        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Format Persis',
            nik: '3171010101900099',
            dateOfBirth: Carbon::parse('1990-01-01'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $this->assertMatchesRegularExpression('/^HSP\/MRN\/\d{6}-\d{5}$/', $res['patient']->mrn);
        $this->assertMatchesRegularExpression('/^HSP\/ENC\/\d{6}-\d{5}$/', $res['encounter']->encounter_code);

        // 2. RX
        $rxAction = app(CreateOutpatientPrescriptionAction::class);
        $prescription = $rxAction->execute($res['encounter'], new PrescriptionItemDTO(
            drugCode: 'MED-01',
            drugName: 'Paracetamol',
            quantity: 5,
            dosageInstructions: '3x1',
            unitPriceIdr: 2000,
        ), $doctorUser);

        $this->assertMatchesRegularExpression('/^HSP\/RX\/\d{6}-\d{5}$/', $prescription->prescription_code);

        // 3. DSP
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi Utama', 'city' => 'Jakarta']);
        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'MED-01',
            'drug_name' => 'Paracetamol',
            'lot_number' => 'LOT-FMT-01',
            'quantity' => 10,
            'expiry_date' => '2027-12-31',
            'unit_cost_idr' => 1000,
            'is_quarantined' => false,
        ]);

        $pharmacist = $this->createHospitalUser('pharmacist');
        $dspAction = app(DispensePrescriptionFefoAction::class);
        $dispensings = $dspAction->execute($prescription, $branch->id, $pharmacist);

        $this->assertNotEmpty($dispensings);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $dispensings[0]->dispensing_code);

        // 4. INV
        $invAction = app(GenerateOutpatientInvoiceAction::class);
        $invoice = $invAction->execute($res['encounter']);

        $this->assertMatchesRegularExpression('/^HSP\/INV\/\d{6}-\d{5}$/', $invoice->invoice_code);

        // 5. PAY
        $payAction = app(ProcessCashPaymentAction::class);
        $payment = $payAction->execute(new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: $invoice->total_amount_idr,
            idempotencyKey: 'IDEMP-FMT-TEST-001',
            cashierUserId: $cashier->id,
        ));

        $this->assertMatchesRegularExpression('/^HSP\/PAY\/\d{6}-\d{5}$/', $payment->payment_code);

        // 6. CLM-BPJS
        $resBpjs = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien BPJS Persis',
            nik: '3171010101900098',
            dateOfBirth: Carbon::parse('1992-05-05'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'BPJS',
            bpjsCardNumber: '0009998887771',
        ));
        $invBpjs = $invAction->execute($resBpjs['encounter']);
        $clmAction = app(SubmitBpjsClaimAction::class);
        $claim = $clmAction->execute($invBpjs, 'Q-5-44-0', 180000);

        $this->assertMatchesRegularExpression('/^HSP\/CLM-BPJS\/\d{6}-\d{5}$/', $claim->claim_code);
    }

    public function test_beberapa_nomor_dalam_satu_transaksi_pola_dsp_multi_item_semuanya_berbeda(): void
    {
        Carbon::setTestNow('2026-11-02 11:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_DSP'], ['name' => 'Poli Farmasi DSP', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-DSP-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);
        $pharmacist = $this->createHospitalUser('pharmacist');

        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien DSP Multi Lot',
            nik: '3171010101900077',
            dateOfBirth: Carbon::parse('1985-07-15'),
            bloodType: 'B',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        // Resep butuh 25 tablet
        $rxAction = app(CreateOutpatientPrescriptionAction::class);
        $prescription = $rxAction->execute($res['encounter'], new PrescriptionItemDTO(
            drugCode: 'AMX-500',
            drugName: 'Amoxicillin 500mg',
            quantity: 25,
            dosageInstructions: '3x1',
            unitPriceIdr: 3000,
        ), $doctorUser);

        // Buat 3 lot berbeda yang akan diambil secara bertahap oleh alokasi FEFO:
        // Lot 1: 10 tablet (exp 2027-01-01)
        // Lot 2: 10 tablet (exp 2027-02-01)
        // Lot 3: 10 tablet (exp 2027-03-01)
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi Utama', 'city' => 'Jakarta']);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-01',
            'quantity' => 10,
            'expiry_date' => '2027-01-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-02',
            'quantity' => 10,
            'expiry_date' => '2027-02-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-03',
            'quantity' => 10,
            'expiry_date' => '2027-03-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);

        $dspAction = app(DispensePrescriptionFefoAction::class);
        $dispensings = $dspAction->execute($prescription, $branch->id, $pharmacist);

        // Harus terbagi menjadi 3 dispensing: 10, 10, dan 5
        $this->assertCount(3, $dispensings);

        $codes = array_map(fn (OutpatientDispensing $d) => $d->dispensing_code, $dispensings);

        // Seluruh kode harus unik dalam transaksi yang sama
        $this->assertCount(3, array_unique($codes));

        // Format dan keterurutan nomor dokumen gapless
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[0]);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[1]);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[2]);
        $this->assertNotSame($codes[0], $codes[1]);
        $this->assertNotSame($codes[1], $codes[2]);
    }

    public function test_record_dihapus_nomor_berikutnya_tidak_mengisi_lubang(): void
    {
        Carbon::setTestNow('2026-11-02 12:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_GAP'], ['name' => 'Poli Gapless', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-GAP-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);

        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res1 = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Gap 1',
            nik: '3171010101900011',
            dateOfBirth: Carbon::parse('1991-01-01'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $invAction = app(GenerateOutpatientInvoiceAction::class);
        $inv1 = $invAction->execute($res1['encounter']);
        $inv1Code = $inv1->invoice_code;

        // Ambil urutan nomor dari invoice 1
        preg_match('/-(\d+)$/', $inv1Code, $m1);
        $num1 = (int) $m1[1];

        // Hapus record invoice 1 dari database
        $inv1->delete();
        $this->assertDatabaseMissing('hsp_outpatient_invoices', ['id' => $inv1->id]);

        // Buat invoice kedua untuk kunjungan baru
        $res2 = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Gap 2',
            nik: '3171010101900012',
            dateOfBirth: Carbon::parse('1991-02-02'),
            bloodType: 'B',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $inv2 = $invAction->execute($res2['encounter']);
        $inv2Code = $inv2->invoice_code;

        preg_match('/-(\d+)$/', $inv2Code, $m2);
        $num2 = (int) $m2[1];

        // Nomor berikutnya harus bertambah (tidak mengisi lubang yang ditinggalkan record yang dihapus)
        $this->assertSame($num1 + 1, $num2, "Nomor invoice {$inv2Code} tidak boleh mengisi lubang invoice {$inv1Code}");
    }
}
