<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\QueueTicket;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

#[CoversClass(RegisterPatientAndIssueQueueAction::class)]
class OutpatientRegistrationAndQueueTest extends HospitalAcceptanceHelper
{
    public function test_pasien_baru_dapat_didaftarkan_dengan_nik_terenkripsi_dan_blind_index_unik(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create([
            'code' => 'POLI_INT',
            'name' => 'Poli Penyakit Dalam',
            'is_active' => true,
        ]);

        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'polyclinic_id' => $poli->id,
            'doctor_code' => 'DOC-INT-01',
            'sip_number' => 'SIP/2026/001',
            'specialization' => 'Spesialis Penyakit Dalam',
            'is_active' => true,
        ]);

        $dto = new RegisterPatientDTO(
            nik: '3171012345670001',
            fullName: 'Budi Santoso',
            dateOfBirth: Carbon::parse('1985-05-12'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
            bpjsCardNumber: null,
        );

        $result = $action->execute($dto, $regStaff);

        $this->assertInstanceOf(OutpatientEncounter::class, $result['encounter']);
        $this->assertSame(1, $result['queue']->queue_number);
        $this->assertSame('WAITING', $result['queue']->status);
        $this->assertSame('REGISTERED', $result['encounter']->status);

        // NIK harus tersimpan terenkripsi, bukan plaintext
        $rawPatient = DB::table('hsp_patients')->where('id', $result['encounter']->patient_id)->first();
        $this->assertNotNull($rawPatient);
        $this->assertStringNotContainsString('3171012345670001', $rawPatient->national_id_nik ?? '');
        $this->assertNotNull($rawPatient->nik_bidx ?? $rawPatient->nik_blind_index ?? null);
    }

    public function test_pendaftaran_pasien_dengan_nik_sama_ditolak_agar_tidak_ganda(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $dto = new RegisterPatientDTO(
            nik: '3171012345670002',
            fullName: 'Siti Rahma',
            dateOfBirth: Carbon::parse('1990-08-20'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        );

        // Pendaftaran pertama berhasil
        $action->execute($dto, $regStaff);

        // Pendaftaran kedua pada hari yang sama dengan NIK sama di poli yang sama harus ditolak
        $this->expectException(\DomainException::class);
        $action->execute($dto, $regStaff);
    }

    public function test_pasien_lama_ditemukan_kembali_menggunakan_blind_index_nik(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli1 = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $poli2 = Polyclinic::create(['code' => 'POLI_PED', 'name' => 'Poli Anak', 'is_active' => true]);
        $doc1 = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli1->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $doc2 = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli2->id, 'doctor_code' => 'DOC-02', 'is_active' => true]);

        $nik = '3171012345670003';
        $dto1 = new RegisterPatientDTO(
            nik: $nik,
            fullName: 'Ahmad Fauzi',
            dateOfBirth: Carbon::parse('1988-02-14'),
            bloodType: 'B',
            polyclinicId: $poli1->id,
            doctorId: $doc1->id,
            paymentMethod: 'CASH',
        );

        $first = $action->execute($dto1, $regStaff);
        $patientId = $first['encounter']->patient_id;

        // Kunjungan hari berikutnya ke poli lain memakai NIK yang sama
        Carbon::setTestNow(now()->addDay());

        $dto2 = new RegisterPatientDTO(
            nik: $nik,
            fullName: 'Ahmad Fauzi',
            dateOfBirth: Carbon::parse('1988-02-14'),
            bloodType: 'B',
            polyclinicId: $poli2->id,
            doctorId: $doc2->id,
            paymentMethod: 'CASH',
        );

        $second = $action->execute($dto2, $regStaff);

        // Harus menggunakan record Patient yang sama (tidak membuat record pasien baru)
        $this->assertSame($patientId, $second['encounter']->patient_id);
    }

    public function test_nomor_antrian_diberikan_secara_berurutan_mulai_dari_1_untuk_setiap_poli_per_hari(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poliA = Polyclinic::create(['code' => 'POLI_A', 'name' => 'Poli A', 'is_active' => true]);
        $poliB = Polyclinic::create(['code' => 'POLI_B', 'name' => 'Poli B', 'is_active' => true]);
        $docA = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poliA->id, 'doctor_code' => 'DOC-A', 'is_active' => true]);
        $docB = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poliB->id, 'doctor_code' => 'DOC-B', 'is_active' => true]);

        $resA1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000001', fullName: 'P1', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliA->id, doctorId: $docA->id, paymentMethod: 'CASH'), $regStaff);
        $resA2 = $action->execute(new RegisterPatientDTO(nik: '3171000000000002', fullName: 'P2', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliA->id, doctorId: $docA->id, paymentMethod: 'CASH'), $regStaff);
        $resB1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000003', fullName: 'P3', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliB->id, doctorId: $docB->id, paymentMethod: 'CASH'), $regStaff);

        $this->assertSame(1, $resA1['queue']->queue_number);
        $this->assertSame(2, $resA2['queue']->queue_number);
        $this->assertSame(1, $resB1['queue']->queue_number); // Poli B mulai dari 1
    }

    public function test_dua_pendaftaran_bersamaan_di_poli_yang_sama_menghasilkan_nomor_berurutan_tanpa_lompat(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000011', fullName: 'Concurrent 1', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'A', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $res2 = $action->execute(new RegisterPatientDTO(nik: '3171000000000012', fullName: 'Concurrent 2', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'B', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $numbers = [$res1['queue']->queue_number, $res2['queue']->queue_number];
        sort($numbers);

        $this->assertSame([1, 2], $numbers);
        $this->assertNotSame($res1['queue']->queue_code, $res2['queue']->queue_code);
    }

    public function test_nomor_antrian_poli_otomatis_ter_reset_kembali_ke_1_pada_hari_berikutnya(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        Carbon::setTestNow(Carbon::parse('2026-10-30 08:00:00'));
        $day1P1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000021', fullName: 'Day1 Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'A', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $this->assertSame(1, $day1P1['queue']->queue_number);

        Carbon::setTestNow(Carbon::parse('2026-10-31 08:00:00'));
        $day2P1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000022', fullName: 'Day2 Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'B', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $this->assertSame(1, $day2P1['queue']->queue_number);
    }

    public function test_pembatalan_antrian_hanya_diizinkan_selama_pasien_masih_berstatus_terdaftar(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res = $action->execute(new RegisterPatientDTO(nik: '3171000000000031', fullName: 'Cancel Me', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $encounter = $res['encounter'];
        $this->assertSame('REGISTERED', $encounter->status);

        // Batalkan kunjungan
        $encounter->cancel('Pasien mendadak berhalangan');

        $this->assertSame('CANCELLED', $encounter->fresh()->status);
        $this->assertSame('CANCELLED', $res['queue']->fresh()->status);
    }

    public function test_antrian_yang_sudah_dipanggil_atau_diperiksa_tidak_dapat_dibatalkan(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res = $action->execute(new RegisterPatientDTO(nik: '3171000000000032', fullName: 'Examined Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $encounter = $res['encounter'];
        $encounter->update(['status' => 'EXAMINED']);

        $this->expectException(\DomainException::class);
        $encounter->cancel('Tidak boleh batal setelah diperiksa');
    }
}
