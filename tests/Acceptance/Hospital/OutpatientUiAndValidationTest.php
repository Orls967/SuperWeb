<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\Polyclinic;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientUiAndValidationTest extends HospitalAcceptanceHelper
{
    public function test_halaman_pendaftaran_200_untuk_role_berhak(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $admin = $this->createHospitalUser('admin');

        $responseReg = $this->actingAs($regStaff)->get(route('hospital.outpatient.registration.index'));
        $responseReg->assertOk();

        $responseAdmin = $this->actingAs($admin)->get(route('hospital.outpatient.registration.index'));
        $responseAdmin->assertOk();
    }

    public function test_halaman_pendaftaran_403_untuk_role_lain(): void
    {
        $doctor = $this->createHospitalUser('doctor');
        $pharmacist = $this->createHospitalUser('pharmacist');

        $this->actingAs($doctor)->get(route('hospital.outpatient.registration.index'))->assertForbidden();
        $this->actingAs($pharmacist)->get(route('hospital.outpatient.registration.index'))->assertForbidden();
    }

    public function test_form_pendaftaran_validasi_gagal_kembali_dengan_pesan_bukan_500(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');

        // Form post tanpa data wajib
        $response = $this->actingAs($regStaff)->post(route('hospital.outpatient.registration.store'), [
            'nik' => '', // Kosong -> invalid
        ]);

        $this->assertNotSame(500, $response->status());
        $response->assertRedirect();
        $response->assertSessionHasErrors(['nik']);
    }

    public function test_halaman_antrian_200_untuk_perawat_dan_dokter(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $doctor = $this->createHospitalUser('doctor');

        $this->actingAs($nurse)->get(route('hospital.outpatient.queue.index'))->assertOk();
        $this->actingAs($doctor)->get(route('hospital.outpatient.queue.index'))->assertOk();
    }

    public function test_halaman_kasir_200_untuk_kasir_dan_finance(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $finance = $this->createHospitalUser('hospital_finance');

        $this->actingAs($cashier)->get(route('hospital.outpatient.cashier.index'))->assertOk();
        $this->actingAs($finance)->get(route('hospital.outpatient.cashier.index'))->assertOk();
    }

    public function test_halaman_laporan_harian_200_untuk_finance_dan_auditor(): void
    {
        $finance = $this->createHospitalUser('hospital_finance');
        $auditor = $this->createHospitalUser('auditor');

        $this->actingAs($finance)->get(route('hospital.outpatient.reports.daily'))->assertOk();
        $this->actingAs($auditor)->get(route('hospital.outpatient.reports.daily'))->assertOk();
    }
}
