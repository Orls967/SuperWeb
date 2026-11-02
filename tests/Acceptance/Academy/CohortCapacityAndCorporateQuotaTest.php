<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Edu\Application\Actions\AllocateCorporateQuotaAction;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Application\Actions\SettleCorporateAcademyBillingAction;
use Modules\Edu\Domain\Exceptions\CohortCapacityExceededException;
use Modules\Edu\Domain\Exceptions\CorporateQuotaExceededException;
use Modules\Edu\Domain\Exceptions\IdempotencyConflictException;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduProgram;
use Tests\TestCase;

class CohortCapacityAndCorporateQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_regular_cohort_max_capacity_enforced(): void
    {
        $program = $this->createProgram(3_000_000);
        $cohort = $this->createCohort($program, capacity: 2);

        $action = app(EnrollStudentInCohortAction::class);

        $action->execute([
            'student_party_id' => 'student-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:cap:01',
        ]);
        $action->execute([
            'student_party_id' => 'student-02',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:cap:02',
        ]);

        $this->assertEquals(2, $cohort->fresh()->enrolled_count);

        // Third student exceeds max capacity (2)
        $this->expectException(CohortCapacityExceededException::class);
        $action->execute([
            'student_party_id' => 'student-03',
            'cohort_id' => $cohort->id,
            'idempotency_key' => 'edu:enr:cap:03',
        ]);
    }

    public function test_corporate_quota_allocation_creates_prepaid_quota(): void
    {
        $program = $this->createProgram(5_000_000);

        $action = app(AllocateCorporateQuotaAction::class);
        $quota = $action->execute([
            'corporate_client_id' => 'CORP-MINING-01',
            'program_id' => $program->id,
            'quota_total' => 20,
            'unit_price_minor' => 4_500_000, // discounted bulk B2B price
            'idempotency_key' => 'edu:quota:alloc:01',
        ]);

        $this->assertInstanceOf(EduCorporateQuota::class, $quota);
        $this->assertEquals(20, $quota->quota_total);
        $this->assertEquals(0, $quota->quota_used);
        $this->assertEquals(4_500_000, $quota->unit_price_minor);
    }

    public function test_enrollment_using_corporate_quota_increments_used_count(): void
    {
        $program = $this->createProgram(5_000_000);
        $cohort = $this->createCohort($program, capacity: 50);

        $quotaAction = app(AllocateCorporateQuotaAction::class);
        $quota = $quotaAction->execute([
            'corporate_client_id' => 'CORP-TELCO-01',
            'program_id' => $program->id,
            'quota_total' => 5,
            'unit_price_minor' => 4_000_000,
            'idempotency_key' => 'edu:quota:alloc:02',
        ]);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $enrollment = $enrollAction->execute([
            'student_party_id' => 'emp-telco-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'CORP-TELCO-01',
            'idempotency_key' => 'edu:enr:corp:01',
        ]);

        $this->assertEquals(1, $quota->fresh()->quota_used);
        $this->assertEquals('ENROLLED', $enrollment->status);
        $this->assertEquals(4_000_000, $enrollment->amount_paid_minor);
    }

    public function test_enrollment_exceeding_corporate_quota_is_rejected(): void
    {
        $program = $this->createProgram(5_000_000);
        $cohort = $this->createCohort($program, capacity: 50);

        $quotaAction = app(AllocateCorporateQuotaAction::class);
        $quotaAction->execute([
            'corporate_client_id' => 'CORP-HOSP-01',
            'program_id' => $program->id,
            'quota_total' => 1,
            'unit_price_minor' => 5_000_000,
            'idempotency_key' => 'edu:quota:alloc:hosp:01',
        ]);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        // Student 1 uses the 1 available quota
        $enrollAction->execute([
            'student_party_id' => 'nurse-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'CORP-HOSP-01',
            'idempotency_key' => 'edu:enr:corp:hosp:01',
        ]);

        // Student 2 tries to enroll under exhausted corporate quota
        $this->expectException(CorporateQuotaExceededException::class);
        $enrollAction->execute([
            'student_party_id' => 'nurse-02',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'CORP-HOSP-01',
            'idempotency_key' => 'edu:enr:corp:hosp:02',
        ]);
    }

    public function test_settle_corporate_academy_billing_posts_to_ledger(): void
    {
        $program = $this->createProgram(4_000_000);
        $cohort = $this->createCohort($program, capacity: 50);

        $quotaAction = app(AllocateCorporateQuotaAction::class);
        $quotaAction->execute([
            'corporate_client_id' => 'CORP-RETAIL-01',
            'program_id' => $program->id,
            'quota_total' => 10,
            'unit_price_minor' => 3_500_000,
            'idempotency_key' => 'edu:quota:alloc:ret:01',
        ]);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        for ($i = 1; $i <= 3; $i++) {
            $enrollAction->execute([
                'student_party_id' => "cashier-0{$i}",
                'cohort_id' => $cohort->id,
                'corporate_client_id' => 'CORP-RETAIL-01',
                'idempotency_key' => "edu:enr:corp:ret:0{$i}",
            ]);
        }

        $settleAction = app(SettleCorporateAcademyBillingAction::class);
        $totalBilled = $settleAction->execute(
            cohortId: $cohort->id,
            corporateClientId: 'CORP-RETAIL-01',
            idempotencyKey: 'edu:settle:corp:ret:01'
        );

        $this->assertEquals(10_500_000, $totalBilled); // 3 * 3,500,000

        // Verify ledger postings
        $revenueEntry = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'edu:corporate_revenue:IDR'))
            ->latest('id')
            ->first();
        $this->assertNotNull($revenueEntry);
        $this->assertEquals(10_500_000, (int) $revenueEntry->amount);
    }

    public function test_group_corporate_client_bills_via_intercompany_port(): void
    {
        $program = $this->createProgram(6_000_000);
        $cohort = $this->createCohort($program, capacity: 20);

        $quotaAction = app(AllocateCorporateQuotaAction::class);
        $quotaAction->execute([
            'corporate_client_id' => 'ENT_MINING_OPERATIONS', // Group Conglomerate Entity
            'program_id' => $program->id,
            'quota_total' => 2,
            'unit_price_minor' => 6_000_000,
            'idempotency_key' => 'edu:quota:alloc:ic:01',
        ]);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $enrollAction->execute([
            'student_party_id' => 'miner-01',
            'cohort_id' => $cohort->id,
            'corporate_client_id' => 'ENT_MINING_OPERATIONS',
            'idempotency_key' => 'edu:enr:ic:01',
        ]);

        $settleAction = app(SettleCorporateAcademyBillingAction::class);
        $total = $settleAction->execute(
            cohortId: $cohort->id,
            corporateClientId: 'ENT_MINING_OPERATIONS',
            idempotencyKey: 'edu:settle:ic:01'
        );

        $this->assertEquals(6_000_000, $total);
    }

    public function test_duplicate_enrollment_with_same_key_is_idempotent(): void
    {
        $program = $this->createProgram(2_000_000);
        $cohort = $this->createCohort($program, capacity: 20);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $key = 'edu:enr:dup:01';

        $e1 = $enrollAction->execute([
            'student_party_id' => 'student-dup-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $e2 = $enrollAction->execute([
            'student_party_id' => 'student-dup-01',
            'cohort_id' => $cohort->id,
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($e1->id, $e2->id);
        $this->assertEquals(1, $cohort->fresh()->enrolled_count);
    }

    public function test_conflicting_payload_with_same_key_throws_conflict(): void
    {
        $program = $this->createProgram(2_000_000);
        $cohort1 = $this->createCohort($program, capacity: 20);
        $cohort2 = $this->createCohort($program, capacity: 20);

        $enrollAction = app(EnrollStudentInCohortAction::class);
        $key = 'edu:enr:conflict:key:01';

        $enrollAction->execute([
            'student_party_id' => 'student-conf-01',
            'cohort_id' => $cohort1->id,
            'idempotency_key' => $key,
        ]);

        $this->expectException(IdempotencyConflictException::class);
        $enrollAction->execute([
            'student_party_id' => 'student-conf-02',
            'cohort_id' => $cohort2->id,
            'idempotency_key' => $key,
        ]);
    }

    protected function createProgram(int $tuition): EduProgram
    {
        return EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-'.strtoupper(Str::random(6)),
            'title' => 'Cohort Test Program',
            'industry_sector' => 'ENERGY',
            'tuition_fee_minor' => $tuition,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);
    }

    protected function createCohort(EduProgram $program, int $capacity): EduCohort
    {
        return EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-'.strtoupper(Str::random(6)),
            'program_id' => $program->id,
            'instructor_party_id' => 'instructor-test',
            'max_capacity' => $capacity,
            'enrolled_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }
}
