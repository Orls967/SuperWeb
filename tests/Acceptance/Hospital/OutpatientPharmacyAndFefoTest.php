<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\CreateOutpatientPrescriptionAction;
use Modules\Hospital\Application\Actions\DispensePrescriptionFefoAction;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

#[CoversClass(DispensePrescriptionFefoAction::class)]
#[CoversClass(CreateOutpatientPrescriptionAction::class)]
class OutpatientPharmacyAndFefoTest extends HospitalAcceptanceHelper
{
    protected function createExaminedEncounterFixture(): OutpatientEncounter
    {
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $patient = Patient::create(['mrn' => 'MRN-'.uniqid(), 'name' => 'Pasien Farmasi', 'date_of_birth' => '1992-05-10', 'blood_type' => 'AB', 'passport_hash' => hash('sha256', 'p')]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => 'EXAMINED',
            'encrypted_icd10_code' => 'K30',
        ]);
    }

    public function test_resep_obat_elektronik_hanya_dapat_diterbitkan_oleh_dokter(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $nurse = $this->createHospitalUser('nurse');

        $action = app(CreateOutpatientPrescriptionAction::class);
        $dto = new PrescriptionItemDTO(
            drugCode: 'PCT-500',
            drugName: 'Paracetamol 500mg',
            quantity: 10,
            dosageInstructions: '3 x 1 tablet sesudah makan',
        );

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $action->execute($encounter, $dto, $nurse);
    }

    public function test_penerbitan_resep_obat_tidak_mengurangi_saldo_stok_di_gudang(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $doctorUser = $encounter->doctor->user;

        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Instalasi Farmasi Utama', 'city' => 'Jakarta', 'is_active' => true]);
        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-001',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $action = app(CreateOutpatientPrescriptionAction::class);
        $dto = new PrescriptionItemDTO(
            drugCode: 'AMX-500',
            drugName: 'Amoxicillin 500mg',
            quantity: 20,
            dosageInstructions: '3 x 1 tablet tiap 8 jam',
        );

        $prescription = $action->execute($encounter, $dto, $doctorUser);

        $this->assertSame('PRESCRIBED', $prescription->status);
        $this->assertSame('PRESCRIBED', $encounter->fresh()->status);
        // Stok fisik di gudang harus tetap 100 (belum berkurang saat diresepkan)
        $this->assertSame(100, $lot->fresh()->quantity);
    }

    public function test_penyerahan_obat_oleh_apoteker_memotong_stok_menggunakan_alokasi_fefo(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        // Dua lot: Lot A kedaluwarsa 2 bulan lagi, Lot B kedaluwarsa 6 bulan lagi
        $lotA = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-A-EARLY',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(2)->toDateString(),
            'is_quarantined' => false,
        ]);

        $lotB = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-B-LATE',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 20, // Membutuhkan 20 unit: 15 dari Lot A (habis) + 5 dari Lot B
            'dosage_instructions' => '3x1 tablet',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Verifikasi lot A berkurang 15 (menjadi 0) dan lot B berkurang 5 (menjadi 45)
        $this->assertSame(0, $lotA->fresh()->quantity);
        $this->assertSame(45, $lotB->fresh()->quantity);
        $this->assertSame('DISPENSED', $prescription->fresh()->status);
        $this->assertSame('DISPENSED', $encounter->fresh()->status);
    }

    public function test_batch_obat_yang_telah_kedaluwarsa_dilewati_secara_otomatis(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        // Lot expired
        $expiredLot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'lot_number' => 'LOT-EXP',
            'quantity' => 50,
            'expiry_date' => now()->subDay()->toDateString(), // Kemarin kedaluwarsa
            'is_quarantined' => false,
        ]);

        // Lot valid
        $freshLot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'lot_number' => 'LOT-FRESH',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'quantity' => 10,
            'dosage_instructions' => '1x1 malam hari',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Lot expired tidak boleh tersentuh sama sekali
        $this->assertSame(50, $expiredLot->fresh()->quantity);
        // Lot fresh yang dipotong
        $this->assertSame(40, $freshLot->fresh()->quantity);
    }

    public function test_penyerahan_obat_ditolak_jika_total_stok_tidak_mencukupi(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AZT-250',
            'drug_name' => 'Azithromycin 250mg',
            'lot_number' => 'LOT-AZT',
            'quantity' => 5, // Hanya ada 5
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'AZT-250',
            'drug_name' => 'Azithromycin 250mg',
            'quantity' => 10, // Meminta 10
            'dosage_instructions' => '1x1 selama 5 hari',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);

        $this->expectException(\DomainException::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Stok tidak boleh berkurang sebagian (atomic rollback)
        $this->assertSame(5, $lot->fresh()->quantity);
    }

    public function test_penyerahan_obat_memposting_jurnal_beban_hpp_dan_persediaan_secara_seimbang(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-COST',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
            'unit_cost_idr' => 1200, // HPP per tablet Rp 1.200
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'quantity' => 10, // Total HPP = 10 x Rp 1.200 = Rp 12.000
            'dosage_instructions' => '3x1 tablet',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Assert ledger entry created: DEBIT hsp:cogs_pharmacy:IDR Rp 12.000, CREDIT hsp:inventory_pharmacy:IDR Rp 12.000
        $this->assertLedgerPosted('hsp:cogs_pharmacy:IDR', 12000, 'DEBIT');
        $this->assertLedgerPosted('hsp:inventory_pharmacy:IDR', 12000, 'CREDIT');
    }

    public function test_resep_obat_yang_sudah_diserahkan_tidak_dapat_diserahkan_kembali(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-ONCE',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 10,
            'dosage_instructions' => '3x1',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Eksekusi kedua kali harus ditolak
        $this->expectException(\DomainException::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);
    }
}
