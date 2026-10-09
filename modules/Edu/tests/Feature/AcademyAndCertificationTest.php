<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Edu\Application\Services\AcademyAndCertificationService;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduEnrollment;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'edu:tuition_receivable:IDR' => 'asset',
        'edu:tuition_revenue:IDR' => 'revenue',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Education {$code}",
            ]
        );
    }
});

test('(a) prerequisite tak terpenuhi -> enrollment ditolak dengan exception', function () {
    $service = app(AcademyAndCertificationService::class);

    $basicProg = $service->createProgram([
        'title' => 'Dasar Keselamatan Tambang (K3-Basic)',
        'industry_sector' => 'MINING_HSE',
        'total_sessions' => 5,
        'tuition_fee_minor' => 250000000,
    ]);

    $advProg = $service->createProgram([
        'title' => 'Pengawas Operasional Pertama (POP-Advanced)',
        'industry_sector' => 'MINING_HSE',
        'prerequisite_program_id' => $basicProg->id,
        'total_sessions' => 10,
        'tuition_fee_minor' => 750000000,
    ]);

    $advCohort = $service->openCohort([
        'program_id' => $advProg->id,
        'instructor_party_id' => 'INSTR-CHIEF-01',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-20',
        'max_capacity' => 20,
    ]);

    // Student has not passed basic program
    expect(fn () => $service->enrollStudent([
        'student_party_id' => 'STUDENT-NOVICE-99',
        'cohort_id' => $advCohort->id,
        'amount_paid_minor' => 750000000,
    ]))->toThrow(RuntimeException::class, 'Prerequisite program');
});

test('(b) sertifikat hash valid (SHA-256) & QR terverifikasi', function () {
    $service = app(AcademyAndCertificationService::class);

    $prog = $service->createProgram([
        'title' => 'Teknisi High Voltage EV & BESS',
        'industry_sector' => 'AUTOMOTIVE',
        'total_sessions' => 8,
        'tuition_fee_minor' => 500000000,
    ]);

    $cohort = $service->openCohort([
        'program_id' => $prog->id,
        'instructor_party_id' => 'INSTR-ENG-05',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-15',
    ]);

    $enrollment = $service->enrollStudent([
        'student_party_id' => 'STUDENT-TECH-01',
        'cohort_id' => $cohort->id,
        'amount_paid_minor' => 500000000,
    ]);

    $cert = $service->issueCertificate([
        'enrollment_id' => $enrollment->id,
        'student_party_id' => 'STUDENT-TECH-01',
        'program_id' => $prog->id,
        'skill_competency_code' => 'AUTOSERVE_EV_HV_TECH',
        'expires_at' => Carbon::now()->addYears(2)->toDateString(),
        'cpd_points_earned' => 25,
    ]);

    expect($cert)->toBeInstanceOf(EduCertificate::class)
        ->and(strlen($cert->certificate_hash))->toBe(64) // SHA-256 length
        ->and($cert->qr_verification_url)->toContain("https://verify.ecosystem.id/cert/{$cert->certificate_hash}")
        ->and($cert->status)->toBe('ACTIVE');
});

test('(c) refund pro-rata = tarif per sesi * sisa sesi yang belum dihadiri', function () {
    $service = app(AcademyAndCertificationService::class);

    $prog = $service->createProgram([
        'title' => 'Culinary Executive Chef Boot Camp',
        'industry_sector' => 'CULINARY',
        'total_sessions' => 10,
        'tuition_fee_minor' => 1000000000, // 10 jt IDR = 1 jt per session
    ]);

    $cohort = $service->openCohort([
        'program_id' => $prog->id,
        'instructor_party_id' => 'CHEF-MASTER-01',
        'start_date' => '2026-10-01',
        'end_date' => '2026-11-01',
    ]);

    $enrollment = $service->enrollStudent([
        'student_party_id' => 'STUDENT-CHEF-02',
        'cohort_id' => $cohort->id,
        'amount_paid_minor' => 1000000000,
    ]);

    // Student attends 3 sessions then drops out
    $refunded = $service->processProRataRefund($enrollment->id, 3);

    // Remaining: 7 sessions * 100,000,000 minor = 700,000,000 minor
    expect($refunded)->toBeInstanceOf(EduEnrollment::class)
        ->and($refunded->sessions_attended)->toBe(3)
        ->and($refunded->refund_amount_minor)->toBe(700000000)
        ->and($refunded->status)->toBe('CANCELLED');
});

test('(d) sertifikat expired memblokir penugasan role kritis K3/teknisi', function () {
    $service = app(AcademyAndCertificationService::class);

    $prog = $service->createProgram([
        'title' => 'Sertifikasi Operator Alat Berat Tambang',
        'industry_sector' => 'MINING_HSE',
        'total_sessions' => 12,
        'tuition_fee_minor' => 1200000000,
    ]);

    $cohort = $service->openCohort([
        'program_id' => $prog->id,
        'instructor_party_id' => 'INSTR-MINE-09',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-20',
    ]);

    $enrollment = $service->enrollStudent([
        'student_party_id' => 'WORKER-HEAVY-OPS-07',
        'cohort_id' => $cohort->id,
        'amount_paid_minor' => 1200000000,
    ]);

    // Expired certificate
    $service->issueCertificate([
        'enrollment_id' => $enrollment->id,
        'student_party_id' => 'WORKER-HEAVY-OPS-07',
        'program_id' => $prog->id,
        'skill_competency_code' => 'MINING_HEAVY_RIG_OPERATOR',
        'expires_at' => Carbon::now()->subMonths(1)->toDateString(), // expired last month
    ]);

    $allowed = $service->verifyCompetencyForAssignment('WORKER-HEAVY-OPS-07', 'MINING_HEAVY_RIG_OPERATOR');
    expect($allowed)->toBeFalse();

    // Valid certificate for another worker
    $service->issueCertificate([
        'enrollment_id' => $enrollment->id,
        'student_party_id' => 'WORKER-SAFE-OPS-08',
        'program_id' => $prog->id,
        'skill_competency_code' => 'MINING_HEAVY_RIG_OPERATOR',
        'expires_at' => Carbon::now()->addYear()->toDateString(),
    ]);

    $allowedValid = $service->verifyCompetencyForAssignment('WORKER-SAFE-OPS-08', 'MINING_HEAVY_RIG_OPERATOR');
    expect($allowedValid)->toBeTrue();
});
