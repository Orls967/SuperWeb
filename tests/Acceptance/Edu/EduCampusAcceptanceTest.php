<?php

declare(strict_types=1);

namespace Tests\Acceptance\Edu;

use App\Models\User;
use App\Quality\Audit\Fixtures\EduAuditCorruptionFixture;
use Database\Seeders\EduCampusT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Edu\Application\Services\CampusAcademicService;
use Modules\Edu\Domain\Models\EduCampusClass;
use Modules\Edu\Domain\Models\EduCampusCourse;
use Modules\Edu\Domain\Models\EduCampusTerm;
use Modules\Edu\Domain\Models\EduCampusTranscript;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Tests\TestCase;

final class EduCampusAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(EduCampusT1Seeder::class);
    }

    public function test_akses_kontrol_akses_berbasis_peran_untuk_rute_kampus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lecturer = User::factory()->create(['role' => 'lecturer']);
        $student = User::factory()->create(['role' => 'student']);
        $unauthorized = User::factory()->create(['role' => 'farmer']);

        // Rute institusi kampus hanya admin & dosen
        $this->actingAs($unauthorized)->get(route('edu.campus.institutions.index'))
            ->assertForbidden();

        $this->actingAs($student)->get(route('edu.campus.institutions.index'))
            ->assertForbidden();

        $this->actingAs($lecturer)->get(route('edu.campus.institutions.index'))
            ->assertOk();

        $this->actingAs($admin)->get(route('edu.campus.institutions.index'))
            ->assertOk();

        // Rute KRS & Transkrip dapat diakses mahasiswa
        $this->actingAs($student)->get(route('edu.campus.krs.index'))
            ->assertOk();

        $this->actingAs($student)->get(route('edu.campus.transcripts.index'))
            ->assertOk();
    }

    public function test_alur_utama_krs_validasi_prasyarat_dan_bentrok_jadwal_interval_index(): void
    {
        $service = app(CampusAcademicService::class);
        $term = EduCampusTerm::firstOrFail();
        $student = User::where('email', 'student.campus@superweb.test')->firstOrFail();

        $classAlgo = EduCampusClass::where('class_code', 'CS101-A')->firstOrFail();
        $classDb = EduCampusClass::where('class_code', 'CS202-A')->firstOrFail();
        $classClash = EduCampusClass::where('class_code', 'CS201-CLASH')->firstOrFail();

        // 1. Sukses KRS: CS-101 (08:00-09:40) dan CS-202 (10:00-11:40) tidak bentrok
        $enrollments = $service->submitKrs(
            studentUserId: $student->id,
            termId: $term->id,
            classIds: [$classAlgo->id, $classDb->id],
            previousGpa: 3.50
        );

        $this->assertCount(2, $enrollments);
        $this->assertEquals(1, $classAlgo->fresh()->enrolled_count);
        $this->assertEquals(1, $classDb->fresh()->enrolled_count);

        // 2. Gagal KRS karena Bentrok Jadwal (IntervalIndex) dengan CS201-CLASH (09:00-11:00)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('bentrok jadwal kuliah');

        $service->submitKrs(
            studentUserId: $student->id,
            termId: $term->id,
            classIds: [$classAlgo->id, $classClash->id],
            previousGpa: 3.50
        );
    }

    public function test_aturan_bisnis_batas_sks_berdasarkan_ipk_dan_validasi_prasyarat(): void
    {
        $service = app(CampusAcademicService::class);
        $term = EduCampusTerm::firstOrFail();

        // Mahasiswa baru tanpa kelulusan prasyarat CS-101
        $freshStudent = User::factory()->create(['role' => 'student']);

        $classStruct = EduCampusClass::where('class_code', 'CS201-CLASH')->firstOrFail();

        // Gagal karena belum lulus prasyarat CS-101
        try {
            $service->submitKrs(
                studentUserId: $freshStudent->id,
                termId: $term->id,
                classIds: [$classStruct->id],
                previousGpa: 3.00
            );
            $this->fail('Harusnya gagal karena prasyarat CS-101 belum lulus.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('membutuhkan prasyarat CS-101', $e->getMessage());
        }

        // Uji rumus batas SKS berdasarkan IPK
        $this->assertEquals(24, $service->calculateMaxCreditsByGpa(3.80));
        $this->assertEquals(21, $service->calculateMaxCreditsByGpa(2.75));
        $this->assertEquals(18, $service->calculateMaxCreditsByGpa(2.20));
        $this->assertEquals(15, $service->calculateMaxCreditsByGpa(1.80));
    }

    public function test_tagihan_ukt_beasiswa_terbatas_ledger_dan_amortisasi_hamilton(): void
    {
        $service = app(CampusAcademicService::class);
        $term = EduCampusTerm::firstOrFail();
        $student = User::factory()->create(['role' => 'student']);

        // 1. Penagihan UKT kotor 10.000.000 dengan beasiswa 3.000.000 -> netto 7.000.000
        $invoice = $service->issueTuitionInvoice(
            studentUserId: $student->id,
            termId: $term->id,
            grossTuitionIdr: 10000000,
            scholarshipDeductionIdr: 3000000,
            idempotencyKey: 'test-inv-ukt-01'
        );

        $this->assertEquals(10000000, $invoice->gross_amount_idr);
        $this->assertEquals(3000000, $invoice->scholarship_deduction_idr);
        $this->assertEquals(7000000, $invoice->net_payable_idr);

        // 2. Beasiswa melebihi tagihan kotor harus ditolak
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak boleh melebihi UKT kotor');

        $service->issueTuitionInvoice(
            studentUserId: $student->id,
            termId: $term->id,
            grossTuitionIdr: 5000000,
            scholarshipDeductionIdr: 6000000,
            idempotencyKey: 'test-inv-ukt-invalid'
        );

        // 3. Amortisasi cicilan Hamilton untuk Rp7.000.000 dalam 3 termin
        $installments = $service->calculateInstallmentsHamilton(7000000, 3);
        $this->assertEquals(7000000, array_sum($installments));
        $this->assertCount(3, $installments);
    }

    public function test_transkrip_nilai_append_only_dan_hash_chain_kanonikal(): void
    {
        $service = app(CampusAcademicService::class);
        $term = EduCampusTerm::firstOrFail();
        $courseDb = EduCampusCourse::where('course_code', 'CS-202')->firstOrFail();
        $student = User::where('email', 'student.campus@superweb.test')->firstOrFail();

        // Kunci nilai baru untuk CS-202
        $transcript2 = $service->lockGradeToTranscript(
            studentUserId: $student->id,
            courseId: $courseDb->id,
            termId: $term->id,
            gradePoint: 3.50,
            letterGrade: 'B+'
        );

        // Ambil transkrip sebelumnya
        $firstTranscript = EduCampusTranscript::where('student_user_id', $student->id)
            ->where('id', '<', $transcript2->id)
            ->firstOrFail();

        // Verifikasi sambungan rantai hash
        $this->assertEquals(CanonicalHashChain::GENESIS_PREV_HASH, $firstTranscript->prev_hash);
        $this->assertEquals($firstTranscript->certificate_hash, $transcript2->prev_hash);

        // Ulangi kunci mata kuliah yang sama harus ditolak
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('sudah terkunci');

        $service->lockGradeToTranscript(
            studentUserId: $student->id,
            courseId: $courseDb->id,
            termId: $term->id,
            gradePoint: 4.00,
            letterGrade: 'A'
        );
    }

    public function test_audit_dua_sumber_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        // 1. Audit kondisi bersih
        $this->artisan('edu:audit')->assertSuccessful();

        // 2. Korupsi data via fixture
        $fixture = new EduAuditCorruptionFixture;
        $keyword = $fixture->corrupt();

        // 3. Audit harus mendeteksi korupsi dan exit non-zero
        $this->artisan('edu:audit')
            ->assertFailed()
            ->expectsOutputToContain($keyword);
    }
}
