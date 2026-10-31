<?php

declare(strict_types=1);

namespace Modules\Edu\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduHeadhunterFee;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduResearchGrant;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;

class EduAuditCommand extends Command
{
    protected $signature = 'edu:audit {--json : Output discrepancies as JSON}';

    protected $description = 'Audit modul Edu: kapasitas cohort, keabsahan hash sertifikat, penomoran gapless, kuota korporat, dan rekonsiliasi escrow';

    public function handle(): int
    {
        $this->info('Starting Edu Academy & Talent Operations Two-Source Audit (edu:audit)...');

        $discrepancies = 0;
        $errorMessages = [];

        // 1. Verify Cohort Capacity Constraint (PR2 / Anti-Oversubscription)
        $overCapacityCohorts = EduCohort::whereColumn('enrolled_count', '>', 'max_capacity')->get();
        foreach ($overCapacityCohorts as $c) {
            $msg = "COHORT_OVERCAPACITY: Cohort {$c->cohort_code} enrolled count ({$c->enrolled_count}) exceeds max capacity ({$c->max_capacity})";
            $this->error($msg);
            $errorMessages[] = $msg;
            $discrepancies++;
        }

        // 2. Verify Certificate vs Passed Exam Attempts (PR4 Fact-over-derivative)
        $certificates = EduCertificate::with('examAttempt')->get();
        foreach ($certificates as $cert) {
            $hasPassedAttempt = false;

            if ($cert->exam_attempt_id && $cert->examAttempt) {
                $hasPassedAttempt = (bool) $cert->examAttempt->is_passed;
            } elseif ($cert->student_user_id || $cert->student_party_id) {
                $hasPassedAttempt = EduExamAttempt::where(function ($q) use ($cert) {
                    if ($cert->student_user_id) {
                        $q->where('student_user_id', $cert->student_user_id);
                    }
                    if ($cert->student_party_id) {
                        $q->orWhere('student_party_uuid', $cert->student_party_id);
                    }
                })
                    ->where('is_passed', true)
                    ->exists();
            }

            if (! $hasPassedAttempt) {
                $msg = "CERTIFICATE_WITHOUT_PASSED_ATTEMPT: Certificate {$cert->certificate_number} was issued without a passed exam attempt";
                $this->error($msg);
                $errorMessages[] = $msg;
                $discrepancies++;
            }
        }

        // 3. Verify Gapless Certificate Numbering Sequence per Program per Year (S4/PR2)
        $certsByProgram = $certificates->groupBy('program_id');
        foreach ($certsByProgram as $progId => $progCerts) {
            $seqs = [];
            foreach ($progCerts as $cert) {
                $parts = explode('-', $cert->certificate_number);
                $lastPart = end($parts);
                if (is_numeric($lastPart)) {
                    $seqs[] = (int) $lastPart;
                }
            }

            sort($seqs);
            for ($i = 1; $i <= count($seqs); $i++) {
                if ($seqs[$i - 1] !== $i) {
                    $msg = "CERTIFICATE_SEQUENCE_GAP: Program {$progId} has a gap in certificate sequence numbering: expected {$i}, found {$seqs[$i - 1]}";
                    $this->error($msg);
                    $errorMessages[] = $msg;
                    $discrepancies++;
                    break;
                }
            }
        }

        // 4. Verify Canonical Hash-Chain Integrity per Program (B0''.8 CanonicalHashChain)
        foreach ($certsByProgram as $progId => $progCerts) {
            $sortedCerts = $progCerts->sortBy('certificate_number')->values();
            $expectedPrevHash = CanonicalHashChain::GENESIS_PREV_HASH;

            foreach ($sortedCerts as $cert) {
                if ($cert->prev_hash !== $expectedPrevHash) {
                    $msg = "CERTIFICATE_HASH_CHAIN_BROKEN: Certificate {$cert->certificate_number} has invalid prev_hash [{$cert->prev_hash}], expected [{$expectedPrevHash}]";
                    $this->error($msg);
                    $errorMessages[] = $msg;
                    $discrepancies++;
                    break;
                }
                $expectedPrevHash = $cert->certificate_hash;
            }
        }

        // 5. Verify Corporate Quota Used vs Actual Enrollments (Two-Source)
        $corporateQuotas = EduCorporateQuota::all();
        foreach ($corporateQuotas as $quota) {
            $actualEnrollments = EduEnrollment::where('corporate_quota_id', $quota->id)->count();
            if ($quota->quota_used !== $actualEnrollments) {
                $msg = "CORPORATE_QUOTA_MISMATCH: Quota {$quota->id} for client {$quota->corporate_client_id} has recorded quota_used ({$quota->quota_used}) != actual enrollments ({$actualEnrollments})";
                $this->error($msg);
                $errorMessages[] = $msg;
                $discrepancies++;
            }
        }

        // 6. Verify Headhunter Escrow Ledger Reconciliation (Two-Source)
        $totalHeldFees = (int) EduHeadhunterFee::where('status', 'HELD')->sum('fee_minor');
        $escrowAccount = EduAccounts::ESCROW_HEADHUNTER_GUARANTEE;
        $escrowBalance = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $escrowAccount))->sum('amount');

        if ($totalHeldFees !== $escrowBalance) {
            $msg = "HEADHUNTER_ESCROW_MISMATCH: Total held headhunter fees ({$totalHeldFees}) does not match escrow ledger balance ({$escrowBalance})";
            $this->error($msg);
            $errorMessages[] = $msg;
            $discrepancies++;
        }

        // 7. Verify Research Grants Overdraw (Legacy)
        $overdrawnGrants = EduResearchGrant::whereColumn('disbursed_grant_minor', '>', 'total_grant_minor')->count();
        if ($overdrawnGrants > 0) {
            $msg = "RESEARCH_GRANT_OVERDRAW: Found {$overdrawnGrants} research grants exceeding total budget";
            $this->error($msg);
            $errorMessages[] = $msg;
            $discrepancies += $overdrawnGrants;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $discrepancies === 0 ? 'PASSED' : 'FAILED',
                'discrepancies_count' => $discrepancies,
                'errors' => $errorMessages,
            ], JSON_PRETTY_PRINT));
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Educational Programs', EduProgram::count()],
                ['Active Cohorts', EduCohort::count()],
                ['Total Enrollments', EduEnrollment::count()],
                ['Certificates Issued', EduCertificate::count()],
                ['Corporate Quotas', $corporateQuotas->count()],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Edu audit FAILED with {$discrepancies} integrity discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Edu audit PASSED with 0 discrepancies found.');

        return self::SUCCESS;
    }
}
