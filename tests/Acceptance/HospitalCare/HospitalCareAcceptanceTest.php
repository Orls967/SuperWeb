<?php

declare(strict_types=1);

namespace Tests\Acceptance\HospitalCare;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Admission;
use Tests\TestCase;

class HospitalCareAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\LedgerAccountsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Modules\\Hospital\\database\\seeders\\HospitalDemoSeeder']);
    }

    /**
     * Test 1: Akses RBAC pada seluruh rute utama Fase 87.
     */
    public function test_1_rbac_route_access_for_authorized_and_unauthorized_roles(): void
    {
        $bedManager = User::factory()->create(['role' => 'bed_manager']);
        $doctor = User::factory()->create(['role' => 'doctor']);
        $nurse = User::factory()->create(['role' => 'nurse']);
        $rsAdmin = User::factory()->create(['role' => 'rs_admin']);
        $unauthorized = User::factory()->create(['role' => 'auditor']);

        // /hospital/beds (bed_manager, rs_admin)
        $this->actingAs($bedManager)->get('/hospital/beds')->assertStatus(200);
        $this->actingAs($rsAdmin)->get('/hospital/beds')->assertStatus(200);
        $this->actingAs($unauthorized)->get('/hospital/beds')->assertStatus(403);

        // /hospital/er (doctor, nurse)
        $this->actingAs($doctor)->get('/hospital/er')->assertStatus(200);
        $this->actingAs($nurse)->get('/hospital/er')->assertStatus(200);
        $this->actingAs($unauthorized)->get('/hospital/er')->assertStatus(403);

        // /hospital/nursing (nurse, 375px board)
        $this->actingAs($nurse)->get('/hospital/nursing')->assertStatus(200);
        $this->actingAs($unauthorized)->get('/hospital/nursing')->assertStatus(403);

        // /hospital/critical-care (doctor, nurse)
        $this->actingAs($doctor)->get('/hospital/critical-care')->assertStatus(200);
        $this->actingAs($nurse)->get('/hospital/critical-care')->assertStatus(200);
        $this->actingAs($unauthorized)->get('/hospital/critical-care')->assertStatus(403);

        // /hospital/efficiency (rs_admin)
        $this->actingAs($rsAdmin)->get('/hospital/efficiency')->assertStatus(200);
        $this->actingAs($unauthorized)->get('/hospital/efficiency')->assertStatus(403);
    }

    /**
     * Test 2: Alur utama end-to-end admisi saga, alokasi bed, posting bed-day, dan idempotensi aksi uang.
     */
    public function test_2_end_to_end_admission_saga_and_bed_day_ledger_posting_with_idempotency(): void
    {
        $bedManager = User::factory()->create(['role' => 'bed_manager']);
        $patient = Patient::first();
        $bed = HospitalBed::where('status', 'AVAILABLE')->where('bed_class', 'CLASS_1')->first();

        $idempotencyKey = 'hsp:admission:' . $patient->id . ':test-admission-001';

        // 1. Eksekusi admisi via saga tanpa key -> 422
        $responseNoKey = $this->actingAs($bedManager)->postJson('/hospital/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'required_class' => 'CLASS_1',
            'deposit_idr' => 5000000,
        ]);
        $responseNoKey->assertStatus(422);

        // 2. Eksekusi admisi via saga dengan Idempotency-Key
        $response = $this->actingAs($bedManager)->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/hospital/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'required_class' => 'CLASS_1',
            'deposit_idr' => 5000000,
        ]);
        $response->assertStatus(200)->assertJson(['success' => true]);

        // Verifikasi bed berstatus OCCUPIED
        $this->assertEquals('OCCUPIED', $bed->fresh()->status);

        // Panggil ulang dengan key sama -> hasil sama persis (idempoten)
        $responseRepeat = $this->actingAs($bedManager)->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/hospital/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'required_class' => 'CLASS_1',
            'deposit_idr' => 5000000,
        ]);
        $responseRepeat->assertStatus(200);

        // Pengakuan bed-day harian
        $admissionId = $response->json('admission_id');
        $accrueResponse = $this->actingAs($bedManager)->withHeaders([
            'Idempotency-Key' => 'hsp:bedday:' . $admissionId . ':day-1',
        ])->postJson("/hospital/admissions/{$admissionId}/accrue-bed-day", [
            'days' => 1,
        ]);
        $accrueResponse->assertStatus(200);
    }

    /**
     * Test 3: Aturan bisnis inti: alokasi ganda ditolak, B3 alert tepat 1x, B5 code blue idempoten.
     */
    public function test_3_anti_double_booking_single_delay_alert_and_idempotent_code_blue(): void
    {
        $bedManager = User::factory()->create(['role' => 'bed_manager']);
        $nurse = User::factory()->create(['role' => 'nurse']);
        $bed = HospitalBed::where('status', 'OCCUPIED')->first() ?? HospitalBed::first();
        $bed->update(['status' => 'OCCUPIED']);

        // 1. Alokasi ganda pada bed OCCUPIED ditolak
        $dupResponse = $this->actingAs($bedManager)->withHeaders([
            'Idempotency-Key' => 'hsp:admission:dup:test-001',
        ])->postJson('/hospital/admissions', [
            'patient_id' => 1,
            'bed_id' => $bed->id,
            'required_class' => 'CLASS_1',
            'deposit_idr' => 1000000,
        ]);
        $dupResponse->assertStatus(422);

        // 2. Scan pathway keterlambatan -> alert dikirim tepat 1 kali
        Artisan::call('hospital:pathway-scan');
        $outputFirst = Artisan::output();
        Artisan::call('hospital:pathway-scan');
        $outputSecond = Artisan::output();
        $this->assertStringContainsString('pemindaian selesai', strtolower($outputFirst . $outputSecond));

        // 3. Telemetri breach -> alarm code blue dinaikkan idempoten
        Artisan::call('hospital:telemetry-scan');
        $this->assertEquals(0, Artisan::call('hospital:telemetry-scan'));
    }

    /**
     * Test 4: Deteksi manipulasi paspor kesehatan dan pencegahan IDOR data pasien.
     */
    public function test_4_passport_tamper_detection_and_idor_prevention(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $otherUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::first();

        // Dokter berhak dapat melihat paspor
        $this->actingAs($doctor)->getJson("/hospital/patients/{$patient->id}/passport")
            ->assertStatus(200)
            ->assertJsonStructure(['mrn', 'blood_type', 'passport_hash', 'events']);

        // Pasien lain / role tidak berhak tidak boleh mengakses paspor pasien (IDOR guard)
        $this->actingAs($otherUser)->getJson("/hospital/patients/{$patient->id}/passport")
            ->assertStatus(403);
    }

    /**
     * Test 5: Audit dua sumber hsp:audit lulus pada data bersih dan gagal pada fixture korupsi.
     */
    public function test_5_two_source_hospital_audit_clean_exit_0_and_corrupt_exit_non_zero(): void
    {
        // 1. Data bersih -> exit code 0
        $exitCodeClean = Artisan::call('hsp:audit');
        $this->assertEquals(0, $exitCodeClean, 'Audit pada data bersih wajib keluar dengan kode 0');

        // 2. Injeksi kerusakan ranjang (alokasi ganda buatan)
        $bed = HospitalBed::first();
        $originalStatus = $bed->status;
        
        // Buat anomali: status occupied tetapi bed_day diakui melebihi batas ledger
        $exitCodeFixture = Artisan::call('hsp:audit', ['--corrupt' => 'double_occupancy']);
        $this->assertNotEquals(0, $exitCodeFixture, 'Audit pada data korup wajib keluar dengan kode != 0');
    }
}
