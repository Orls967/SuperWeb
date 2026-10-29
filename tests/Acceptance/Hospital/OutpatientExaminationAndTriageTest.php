<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\ExaminePatientAndDiagnoseAction;
use Modules\Hospital\Application\Actions\RecordVitalSignsAction;
use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientExaminationAndTriageTest extends HospitalAcceptanceHelper
{
    protected function createEncounterFixture(Doctor $doctor, string $status = 'REGISTERED'): OutpatientEncounter
    {
        $patient = Patient::create([
            'mrn' => 'MRN-'.uniqid(),
            'name' => 'Pasien Tes',
            'date_of_birth' => '1990-01-01',
            'blood_type' => 'O',
            'passport_hash' => hash('sha256', 'test-passport'),
        ]);

        $queue = QueueTicket::create([
            'polyclinic_id' => $doctor->polyclinic_id,
            'doctor_id' => $doctor->id,
            'queue_date' => now()->toDateString(),
            'queue_number' => 1,
            'queue_code' => 'TICKET-001',
            'status' => 'WAITING',
        ]);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $doctor->polyclinic_id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => $status,
        ]);
    }

    public function test_perawat_poli_dapat_mencatat_tanda_tanda_vital_pasien_pada_tahap_triage(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $triageAction = app(RecordVitalSignsAction::class);
        $dto = new VitalSignsDTO(
            systolic: 120,
            diastolic: 80,
            pulse: 78,
            respiratory: 18,
            temperature: 36.6,
        );

        $updated = $triageAction->execute($encounter, $dto, $nurse);

        $this->assertSame(120, $updated->systolic_bp);
        $this->assertSame(80, $updated->diastolic_bp);
        $this->assertSame(78, $updated->pulse_rate);
        $this->assertSame(18, $updated->respiratory_rate);
        $this->assertEquals(36.6, (float) $updated->body_temp_c);
    }

    public function test_perawat_poli_ditolak_saat_mencoba_memasukkan_diagnosis_medis(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Catatan diagnosa terlarang oleh perawat',
            icd10DiagnosisCode: 'K30',
        );

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $action->execute($encounter, $dto, $nurse);
    }

    public function test_hanya_dokter_yang_bertugas_pada_kunjungan_yang_dapat_mengisi_diagnosis(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pasien mengalami dispepsia fungsional berulang',
            icd10DiagnosisCode: 'K30',
        );

        $examined = $action->execute($encounter, $dto, $doctorUser);

        $this->assertSame('EXAMINED', $examined->status);
        $this->assertSame('K30', $examined->encrypted_icd10_code);
        $this->assertSame('Pasien mengalami dispepsia fungsional berulang', $examined->encrypted_doctor_notes);
        $this->assertNotNull($examined->examined_at);
    }

    public function test_dokter_dari_poli_lain_ditolak_saat_mencoba_mengubah_rekam_medis_kunjungan_pasien(): void
    {
        $docUser1 = $this->createHospitalUser('doctor');
        $docUser2 = $this->createHospitalUser('doctor');

        $poli1 = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $poli2 = Polyclinic::create(['code' => 'POLI_PED', 'name' => 'Poli Anak', 'is_active' => true]);

        $doctor1 = Doctor::create(['user_id' => $docUser1->id, 'polyclinic_id' => $poli1->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $doctor2 = Doctor::create(['user_id' => $docUser2->id, 'polyclinic_id' => $poli2->id, 'doctor_code' => 'DOC-02', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor1, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pemeriksaan liar dokter luar',
            icd10DiagnosisCode: 'J00',
        );

        // Dokter 2 mencoba memeriksa pasien dokter 1
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $action->execute($encounter, $dto, $docUser2);
    }

    public function test_diagnosis_icd10_divalidasi_format_kodenya(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Catatan dokter',
            icd10DiagnosisCode: 'INVALID-CODE-XYZ!@#',
        );

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($encounter, $dto, $doctorUser);
    }

    public function test_catatan_klinis_dokter_tersimpan_dalam_keadaan_terenkripsi_di_database(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $secretClinicalNote = 'Pasien memiliki riwayat rahasia medis keluarga kronis';
        $dto = new DoctorExaminationDTO(
            doctorNotes: $secretClinicalNote,
            icd10DiagnosisCode: 'I10',
        );

        $action->execute($encounter, $dto, $doctorUser);

        // Query database secara mentah tanpa Eloquent decryptor
        $rawRecord = DB::table('hsp_outpatient_encounters')->where('id', $encounter->id)->first();
        $this->assertNotNull($rawRecord);
        $this->assertStringNotContainsString($secretClinicalNote, $rawRecord->encrypted_doctor_notes);
        $this->assertStringNotContainsString('I10', $rawRecord->encrypted_icd10_code);
    }

    public function test_pemeriksaan_dokter_ditolak_jika_pasien_belum_dipanggil_dari_antrian(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        // Pasien masih berstatus REGISTERED (belum CALLED)
        $encounter = $this->createEncounterFixture($doctor, status: 'REGISTERED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pemeriksaan loncat antrian',
            icd10DiagnosisCode: 'K30',
        );

        $this->expectException(\DomainException::class);
        $action->execute($encounter, $dto, $doctorUser);
    }
}
