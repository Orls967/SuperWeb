<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Edu\Application\Actions\AddProgramPrerequisiteAction;
use Modules\Edu\Application\Actions\AllocateCorporateQuotaAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Application\Actions\EvaluateProctoredExamAttemptAction;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Application\Actions\MatchTalentPoolAction;
use Modules\Edu\Application\Actions\SettleHeadhunterFeeAction;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduHeadhunterFee;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduTalentPreference;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\CanonicalHashChainVerifier;
use RuntimeException;

/**
 * L16.4: Deterministic 6-Month Simulation Runner for Academy & Talent Line (Lini 16).
 * Enforces PR3 Determinism: two runs with identical seed produce identical state hash and metrics.
 */
class AcademySimulationRunner
{
    public function __construct(
        protected AddProgramPrerequisiteAction $addPrereqAction,
        protected AllocateCorporateQuotaAction $allocateQuotaAction,
        protected EnrollStudentInCohortAction $enrollAction,
        protected EvaluateProctoredExamAttemptAction $evaluateAttemptAction,
        protected IssueVerifiedCompetencyCertificateAction $issueCertAction,
        protected MatchTalentPoolAction $matchTalentAction,
        protected SettleHeadhunterFeeAction $settleHeadhunterAction
    ) {}

    /**
     * Run deterministic 6-month simulation of Education & Talent (Lini 16).
     *
     * @return array{
     *     seed: int,
     *     months: int,
     *     programs_count: int,
     *     cohorts_count: int,
     *     corporate_quotas_allocated: int,
     *     total_enrollments: int,
     *     corporate_enrollments: int,
     *     direct_enrollments: int,
     *     total_exam_attempts: int,
     *     passed_exam_attempts: int,
     *     failed_exam_attempts: int,
     *     certificates_issued: int,
     *     talent_matches_placed: int,
     *     headhunter_escrows_held: int,
     *     headhunter_escrows_captured: int,
     *     total_tuition_billed_minor: int,
     *     total_headhunter_fees_captured_minor: int,
     *     audit_exit_code: int,
     *     bank_reconcile_exit_code: int,
     *     chain_valid: bool,
     *     state_hash: string
     * }
     */
    public function run(
        int $months = 6,
        int $programsCount = 40,
        int $cohortsCount = 200,
        int $enrollmentsCount = 8000,
        int $attemptsCount = 20000,
        int $seed = 161616
    ): array {
        mt_srand($seed);

        if (LedgerAccount::count() === 0) {
            $seeder = new LedgerAccountsSeeder;
            $seeder->run();
        }

        $startDate = CarbonImmutable::parse('2026-01-01 00:00:00');

        // 1. Create 40 Programs across 8 sectors
        $sectors = ['ENERGY', 'MINING', 'MANUFACTURING', 'TELCO', 'RETAIL', 'HEALTHCARE', 'IT', 'FINANCE'];
        $programs = [];

        for ($p = 1; $p <= $programsCount; $p++) {
            $sector = $sectors[($p - 1) % count($sectors)];
            $code = sprintf('PRG-SIM-%03d', $p);
            $feeMinor = 2_500_000 + (($p * 37) % 5) * 500_000;

            $program = EduProgram::create([
                'id' => sprintf('00000000-0001-4000-8000-%012d', $p),
                'program_code' => $code,
                'title' => "Curriculum Program {$p} ({$sector})",
                'industry_sector' => $sector,
                'tuition_fee_minor' => $feeMinor,
                'passing_score' => 75,
                'validity_months' => 24,
                'status' => 'ACTIVE',
            ]);

            $programs[] = $program;
        }

        $programIndexById = [];
        foreach ($programs as $idx => $p) {
            $programIndexById[$p->id] = $idx;
        }

        // Establish DAG prerequisites: every 4th program (4, 8, 12...) depends on the (p-1)th program
        for ($p = 2; $p <= $programsCount; $p++) {
            if ($p % 4 === 0) {
                $this->addPrereqAction->execute(
                    program: $programs[$p - 1],
                    prerequisiteProgram: $programs[$p - 2]
                );
            }
        }

        // 2. Create 200 Cohorts (5 per program)
        $cohorts = [];
        $cohortIndex = 1;
        $cohortsPerProgram = (int) ($cohortsCount / $programsCount);

        foreach ($programs as $prog) {
            for ($c = 1; $c <= $cohortsPerProgram; $c++) {
                $cohortCode = sprintf('CHT-SIM-%04d', $cohortIndex++);
                $cohort = EduCohort::create([
                    'id' => sprintf('00000000-0002-4000-8000-%012d', $cohortIndex),
                    'cohort_code' => $cohortCode,
                    'program_id' => $prog->id,
                    'instructor_party_id' => 'trainer-'.(($cohortIndex % 10) + 1),
                    'start_date' => $startDate->addDays($cohortIndex % 30)->toDateString(),
                    'end_date' => $startDate->addDays(($cohortIndex % 30) + 90)->toDateString(),
                    'max_capacity' => 50,
                    'enrolled_count' => 0,
                    'status' => 'OPEN',
                ]);
                $cohorts[] = $cohort;
            }
        }

        // 3. Allocate Corporate Quotas (5,000 quota across 25 foundational programs)
        $corporateClients = [
            ['id' => 'CORP-PLN-01', 'is_group' => true],
            ['id' => 'CORP-TELKOM-01', 'is_group' => false],
            ['id' => 'CORP-MINING-01', 'is_group' => true],
            ['id' => 'CORP-RETAIL-01', 'is_group' => true],
            ['id' => 'CORP-MFG-01', 'is_group' => false],
        ];

        // Pick foundational programs without prerequisites (indices where ($p-1) % 4 !== 3)
        $foundationalPrograms = [];
        foreach ($programs as $idx => $pModel) {
            if ($idx % 4 !== 3) {
                $foundationalPrograms[] = $pModel;
            }
        }

        $quotas = [];
        $totalCorporateAllocated = 0;
        $foundationalCount = count($foundationalPrograms);

        foreach ($corporateClients as $cIdx => $corp) {
            // Allocate 5 programs per client, 200 quota each = 1,000 quota per client
            for ($k = 0; $k < 5; $k++) {
                $pIndex = ($cIdx * 5 + $k) % $foundationalCount;
                $prog = $foundationalPrograms[$pIndex];

                $quotaAlloc = $this->allocateQuotaAction->execute([
                    'id' => sprintf('00000000-0003-4000-8000-%012d', $cIdx * 5 + $k + 1),
                    'corporate_client_id' => $corp['id'],
                    'program_id' => $prog->id,
                    'quota_total' => 200,
                    'unit_price_minor' => 2_000_000,
                    'idempotency_key' => "QUOTA:CORP:{$corp['id']}:{$prog->program_code}",
                ]);

                $quotas[] = $quotaAlloc;
                $totalCorporateAllocated += 200;
            }
        }

        // Pre-create student Users for relational foreign keys
        $users = [];
        for ($u = 1; $u <= 200; $u++) {
            $users[$u] = User::firstOrCreate(
                ['id' => 1000 + $u],
                [
                    'name' => "Student {$u}",
                    'email' => "student{$u}@example.com",
                    'password' => 'static_password_hash',
                ]
            );
        }

        // Tracking state
        $totalEnrollments = 0;
        $corporateEnrollmentsCount = 0;
        $directEnrollmentsCount = 0;
        $totalExamAttempts = 0;
        $passedExamAttempts = 0;
        $failedExamAttempts = 0;
        $certificatesIssued = 0;
        $talentMatchesPlaced = 0;
        $headhunterEscrowsHeld = 0;
        $headhunterEscrowsCaptured = 0;
        $totalTuitionBilledMinor = 0;
        $totalHeadhunterFeesCapturedMinor = 0;

        $chainRecords = [];
        $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;

        $enrollmentsPerMonth = (int) ceil($enrollmentsCount / $months);
        $attemptsPerMonth = (int) ceil($attemptsCount / $months);
        $studentCounter = 1;
        $attemptCounter = 1;

        /** @var list<array{fee_id: string, held_date: CarbonImmutable, captured: bool}> $heldFeeEntries */
        $heldFeeEntries = [];

        // 4. Monthly Simulation Loop (6 Months)
        for ($m = 1; $m <= $months; $m++) {
            $monthDate = $startDate->addMonths($m - 1);
            Carbon::setTestNow($monthDate);

            DB::transaction(function () use (
                $m,
                $monthDate,
                $enrollmentsPerMonth,
                $attemptsPerMonth,
                $cohorts,
                $programs,
                $programIndexById,
                $quotas,
                $users,
                &$studentCounter,
                &$attemptCounter,
                &$totalEnrollments,
                &$corporateEnrollmentsCount,
                &$directEnrollmentsCount,
                &$totalExamAttempts,
                &$passedExamAttempts,
                &$failedExamAttempts,
                &$certificatesIssued,
                &$totalTuitionBilledMinor,
                &$talentMatchesPlaced,
                &$headhunterEscrowsHeld,
                &$headhunterEscrowsCaptured,
                &$totalHeadhunterFeesCapturedMinor,
                &$heldFeeEntries
            ) {
                // A. Enrollments
                $monthEnrollmentTarget = min($enrollmentsPerMonth, 8000 - $totalEnrollments);
                for ($e = 0; $e < $monthEnrollmentTarget; $e++) {
                    $studentId = sprintf('STUDENT-%06d', $studentCounter);
                    $userKey = (($studentCounter - 1) % count($users)) + 1;
                    $studentUser = $users[$userKey];

                    // Determine if corporate (5000 corporate quota)
                    $isCorporate = ($corporateEnrollmentsCount < 5000);
                    $corpQuota = null;
                    $selectedCohort = null;

                    if ($isCorporate) {
                        // Select an available quota with remaining capacity
                        foreach ($quotas as $q) {
                            if ($q->quota_used < $q->quota_total) {
                                $corpQuota = $q;
                                break;
                            }
                        }

                        if ($corpQuota !== null) {
                            // Find cohort for this quota's program
                            foreach ($cohorts as $c) {
                                if ($c->program_id === $corpQuota->program_id && $c->enrolled_count < $c->max_capacity) {
                                    $selectedCohort = $c;
                                    break;
                                }
                            }
                        }
                    }

                    if ($selectedCohort === null) {
                        // Direct enrollment: pick cohort from foundational programs (no unmet prereq)
                        $isCorporate = false;
                        $corpQuota = null;
                        foreach ($cohorts as $c) {
                            $progIdx = $programIndexById[$c->program_id] ?? 0;
                            // avoid programs with prerequisite (every 4th program: index 3, 7, 11...)
                            if ($progIdx % 4 !== 3 && $c->enrolled_count < $c->max_capacity) {
                                $selectedCohort = $c;
                                break;
                            }
                        }

                        if ($selectedCohort === null) {
                            // Check advanced programs with prerequisites (indices 3, 7, 11...)
                            foreach ($cohorts as $c) {
                                $progIdx = $programIndexById[$c->program_id] ?? 0;
                                if ($progIdx % 4 === 3 && $c->enrolled_count < $c->max_capacity) {
                                    $prereqProg = $programs[$progIdx - 1];
                                    $gradCerts = EduCertificate::where('program_id', $prereqProg->id)
                                        ->where('status', 'ACTIVE')
                                        ->orderBy('certificate_number')
                                        ->limit(60)
                                        ->get();

                                    foreach ($gradCerts as $cert) {
                                        $alreadyEnrolled = EduEnrollment::where('cohort_id', $c->id)
                                            ->where('student_party_id', $cert->student_party_id)
                                            ->exists();

                                        if (! $alreadyEnrolled) {
                                            $selectedCohort = $c;
                                            $studentId = $cert->student_party_id;
                                            if ($cert->student_user_id) {
                                                $studentUser = $users[(($cert->student_user_id - 1000 - 1) % count($users)) + 1] ?? $studentUser;
                                            }
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }

                        if ($selectedCohort === null) {
                            throw new RuntimeException("No available cohort with capacity for enrollment index {$totalEnrollments}");
                        }
                    }

                    $idempotencyKey = "ENR:SIM:{$selectedCohort->cohort_code}:{$studentId}";

                    $this->enrollAction->execute([
                        'id' => sprintf('00000000-0005-4000-8000-%012d', $totalEnrollments + 1),
                        'enrollment_code' => sprintf('ENR-SIM-%06d', $totalEnrollments + 1),
                        'cohort_id' => $selectedCohort->id,
                        'student_party_id' => $studentId,
                        'student_user_id' => $studentUser->id,
                        'corporate_client_id' => $corpQuota?->corporate_client_id,
                        'corporate_quota_id' => $corpQuota?->id,
                        'amount_paid_minor' => $isCorporate ? 0 : 2_500_000,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    $selectedCohort->enrolled_count++;
                    if ($corpQuota !== null) {
                        $corpQuota->quota_used++;
                    }

                    $totalEnrollments++;
                    if ($isCorporate) {
                        $corporateEnrollmentsCount++;
                    } else {
                        $directEnrollmentsCount++;
                        $totalTuitionBilledMinor += 2_500_000;
                    }

                    $studentCounter++;
                }

                // B. Proctored Exam Attempts (20,000 attempts total)
                $monthAttemptTarget = min($attemptsPerMonth, 20000 - $totalExamAttempts);
                // Fetch enrollments for attempt association
                $availableEnrollments = EduEnrollment::where('status', 'ENROLLED')
                    ->orderBy('enrollment_code')
                    ->get();

                $enrollmentCount = $availableEnrollments->count();

                for ($a = 0; $a < $monthAttemptTarget; $a++) {
                    $enrollment = $availableEnrollments[$a % $enrollmentCount];
                    $cohort = EduCohort::find($enrollment->cohort_id);
                    $prog = EduProgram::find($cohort->program_id);

                    // Deterministic score (75% pass rate >= 75)
                    $score = 50 + (mt_rand(0, 50));
                    $isPassed = ($score >= $prog->passing_score);

                    $attempt = $this->evaluateAttemptAction->execute([
                        'id' => sprintf('00000000-0006-4000-8000-%012d', $attemptCounter),
                        'enrollment_id' => $enrollment->id,
                        'enrollment_code' => $enrollment->enrollment_code,
                        'student_party_uuid' => $enrollment->student_party_id,
                        'student_user_id' => $enrollment->student_user_id,
                        'exam_title' => "Certification Exam for {$prog->title}",
                        'score' => $score,
                        'passing_score' => $prog->passing_score,
                        'proctor_signature' => "PROCTOR-STAMP-M{$m}",
                        'idempotency_key' => "ATT:SIM:{$attemptCounter}:{$enrollment->id}",
                    ]);

                    $totalExamAttempts++;
                    $attemptCounter++;

                    if ($isPassed) {
                        $passedExamAttempts++;

                        // Issue certificate if student does not yet have one for this program
                        $existingCert = EduCertificate::where('program_id', $prog->id)
                            ->where('student_party_id', $enrollment->student_party_id)
                            ->first();

                        if (! $existingCert) {
                            $this->issueCertAction->execute([
                                'id' => sprintf('00000000-0007-4000-8000-%012d', $certificatesIssued + 1),
                                'enrollment_id' => $enrollment->id,
                                'exam_attempt_id' => $attempt->id,
                                'program_id' => $prog->id,
                                'skill_competency_code' => $prog->program_code,
                                'idempotency_key' => "CERT:SIM:{$enrollment->id}:{$attempt->id}",
                            ]);
                            $certificatesIssued++;
                        }
                    } else {
                        $failedExamAttempts++;
                    }
                }

                // C. Monthly Talent Matching (Gale-Shapley Stable Matching)
                // Post 5 job openings per month
                $monthOpenings = [];
                for ($j = 1; $j <= 5; $j++) {
                    $targetProg = $programs[$j % 10];
                    $opening = EduJobOpening::create([
                        'id' => sprintf('00000000-0004-4000-8000-%012d', ($m * 100) + $j),
                        'opening_code' => sprintf('JOB-SIM-M%d-%02d', $m, $j),
                        'title' => "Specialist Position {$j} (Month {$m})",
                        'hiring_entity' => 'CorpEntity'.(($j % 5) + 1),
                        'location_city' => 'Jakarta',
                        'annual_salary_minor' => 120_000_000,
                        'capacity' => 2,
                        'headhunter_fee_bps' => 1500, // 15%
                        'required_credential_code' => $targetProg->program_code,
                        'status' => 'OPEN',
                    ]);
                    $monthOpenings[] = $opening;
                }

                // Collect graduates with certificates for this program
                $matchedThisMonth = 0;
                foreach ($monthOpenings as $opening) {
                    $eligibleCerts = EduCertificate::where('skill_competency_code', $opening->required_credential_code)
                        ->where('status', 'ACTIVE')
                        ->orderBy('certificate_number')
                        ->limit(3)
                        ->get();

                    foreach ($eligibleCerts as $idx => $cert) {
                        if ($cert->student_user_id) {
                            EduTalentPreference::firstOrCreate(
                                [
                                    'student_user_id' => $cert->student_user_id,
                                    'opening_id' => $opening->id,
                                ],
                                [
                                    'id' => sprintf('00000000-0010-4000-8000-%012s', substr(hash('sha256', "{$cert->student_user_id}:{$opening->id}"), 0, 12)),
                                    'rank_order' => $idx + 1,
                                ]
                            );
                        }
                    }

                    if ($eligibleCerts->count() > 0) {
                        $matchResult = $this->matchTalentAction->execute([$opening->required_credential_code]);
                        $placedCount = count($matchResult['placements'] ?? []);
                        $matchedThisMonth += $placedCount;
                        $talentMatchesPlaced += $placedCount;

                        // Hold headhunter fee for each placement
                        foreach ($matchResult['placements'] as $plc) {
                            $placementModel = EduPlacement::find($plc['id'] ?? $plc->id ?? null);
                            if ($placementModel) {
                                $fee = $this->settleHeadhunterAction->holdFee($placementModel);
                                $headhunterEscrowsHeld++;
                                $heldFeeEntries[] = [
                                    'fee_id' => $fee->id,
                                    'held_date' => $monthDate,
                                    'captured' => false,
                                ];
                            }
                        }
                    }
                }

                // D. Capture Escrows after 90 days (Months 4, 5, 6)
                if ($m >= 4) {
                    $captureAsOf = Carbon::parse($monthDate->toDateString())->addDays(95);
                    foreach ($heldFeeEntries as &$entry) {
                        if (! $entry['captured']) {
                            $daysElapsed = $entry['held_date']->diffInDays($monthDate);
                            if ($daysElapsed >= 90) {
                                $feeModel = EduHeadhunterFee::find($entry['fee_id']);
                                if ($feeModel && $feeModel->status === 'HELD') {
                                    $this->settleHeadhunterAction->captureFee($feeModel, $captureAsOf);
                                    $entry['captured'] = true;
                                    $headhunterEscrowsCaptured++;
                                    $totalHeadhunterFeesCapturedMinor += $feeModel->fee_minor;
                                }
                            }
                        }
                    }
                    unset($entry);
                }
            });

            // Record monthly hash in canonical hash chain
            $monthlyPayload = [
                'month' => $m,
                'total_enrollments' => $totalEnrollments,
                'corporate_enrollments' => $corporateEnrollmentsCount,
                'direct_enrollments' => $directEnrollmentsCount,
                'total_exam_attempts' => $totalExamAttempts,
                'passed_exam_attempts' => $passedExamAttempts,
                'failed_exam_attempts' => $failedExamAttempts,
                'certificates_issued' => $certificatesIssued,
                'talent_matches_placed' => $talentMatchesPlaced,
                'headhunter_escrows_held' => $headhunterEscrowsHeld,
                'headhunter_escrows_captured' => $headhunterEscrowsCaptured,
                'total_tuition_billed_minor' => $totalTuitionBilledMinor,
                'total_headhunter_fees_captured_minor' => $totalHeadhunterFeesCapturedMinor,
            ];

            $monthlyHash = CanonicalHashChain::computeHash($prevHash, $monthlyPayload);
            $chainRecords[] = [
                'prev_hash' => $prevHash,
                'payload' => $monthlyPayload,
                'hash' => $monthlyHash,
            ];
            $prevHash = $monthlyHash;
        }

        Carbon::setTestNow();

        // Audits at the end of Month 6
        $auditExitCode = Artisan::call('edu:audit');
        $bankReconcileExitCode = Artisan::call('bank:reconcile');

        $finalPayload = [
            'seed' => $seed,
            'months' => $months,
            'programs_count' => $programsCount,
            'cohorts_count' => $cohortsCount,
            'total_enrollments' => $totalEnrollments,
            'corporate_enrollments' => $corporateEnrollmentsCount,
            'direct_enrollments' => $directEnrollmentsCount,
            'total_exam_attempts' => $totalExamAttempts,
            'passed_exam_attempts' => $passedExamAttempts,
            'failed_exam_attempts' => $failedExamAttempts,
            'certificates_issued' => $certificatesIssued,
            'talent_matches_placed' => $talentMatchesPlaced,
            'headhunter_escrows_held' => $headhunterEscrowsHeld,
            'headhunter_escrows_captured' => $headhunterEscrowsCaptured,
            'total_tuition_billed_minor' => $totalTuitionBilledMinor,
            'total_headhunter_fees_captured_minor' => $totalHeadhunterFeesCapturedMinor,
            'audit_exit_code' => $auditExitCode,
            'bank_reconcile_exit_code' => $bankReconcileExitCode,
        ];

        $finalHash = CanonicalHashChain::computeHash($prevHash, $finalPayload);
        $chainRecords[] = [
            'prev_hash' => $prevHash,
            'payload' => $finalPayload,
            'hash' => $finalHash,
        ];

        // Verify entire canonical hash chain
        $brokenIndex = CanonicalHashChainVerifier::verifyChain($chainRecords);
        $chainValid = ($brokenIndex === null);

        if (! $chainValid) {
            throw new RuntimeException("Canonical hash chain broken at month index: {$brokenIndex}");
        }

        return [
            'seed' => $seed,
            'months' => $months,
            'programs_count' => $programsCount,
            'cohorts_count' => $cohortsCount,
            'corporate_quotas_allocated' => $totalCorporateAllocated,
            'total_enrollments' => $totalEnrollments,
            'corporate_enrollments' => $corporateEnrollmentsCount,
            'direct_enrollments' => $directEnrollmentsCount,
            'total_exam_attempts' => $totalExamAttempts,
            'passed_exam_attempts' => $passedExamAttempts,
            'failed_exam_attempts' => $failedExamAttempts,
            'certificates_issued' => $certificatesIssued,
            'talent_matches_placed' => $talentMatchesPlaced,
            'headhunter_escrows_held' => $headhunterEscrowsHeld,
            'headhunter_escrows_captured' => $headhunterEscrowsCaptured,
            'total_tuition_billed_minor' => $totalTuitionBilledMinor,
            'total_headhunter_fees_captured_minor' => $totalHeadhunterFeesCapturedMinor,
            'audit_exit_code' => $auditExitCode,
            'bank_reconcile_exit_code' => $bankReconcileExitCode,
            'chain_valid' => $chainValid,
            'state_hash' => $finalHash,
        ];
    }
}
