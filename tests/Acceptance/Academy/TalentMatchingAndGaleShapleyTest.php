<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Edu\Application\Actions\MatchTalentPoolAction;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduTalentPreference;
use Modules\Edu\Domain\Services\CertificateQrSigner;
use Modules\Shared\Domain\Numerics\BlockingPairVerifier;
use Tests\TestCase;

class TalentMatchingAndGaleShapleyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_candidate_proposing_gale_shapley_matching_allocates_candidates(): void
    {
        // 3 candidates, 2 openings (capacity 1 each)
        $cand1 = User::factory()->create(['name' => 'Alice']);
        $cand2 = User::factory()->create(['name' => 'Bob']);
        $cand3 = User::factory()->create(['name' => 'Charlie']);

        $prog = $this->createProgramWithCerts([
            ['user' => $cand1, 'score' => 95],
            ['user' => $cand2, 'score' => 85],
            ['user' => $cand3, 'score' => 78],
        ], 'PRG-AI-01');

        $openingA = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-TELCO-AI-01',
            'hiring_entity' => 'TelcoCorp',
            'title' => 'AI Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 240_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-AI-01',
            'status' => 'OPEN',
        ]);

        $openingB = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-ENERGY-AI-01',
            'hiring_entity' => 'PowerGrid',
            'title' => 'Grid AI Analyst',
            'capacity' => 1,
            'annual_salary_minor' => 200_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-AI-01',
            'status' => 'OPEN',
        ]);

        // Alice prefers A then B
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand1->id, 'opening_id' => $openingA->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand1->id, 'opening_id' => $openingB->id, 'rank_order' => 2]);

        // Bob prefers A then B
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand2->id, 'opening_id' => $openingA->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand2->id, 'opening_id' => $openingB->id, 'rank_order' => 2]);

        // Charlie prefers B then A
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand3->id, 'opening_id' => $openingB->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand3->id, 'opening_id' => $openingA->id, 'rank_order' => 2]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-AI-01']);

        // Assert placements were persisted
        $this->assertCount(2, $result['placements']);
        $this->assertDatabaseHas('edu_placements', [
            'student_user_id' => $cand1->id,
            'opening_id' => $openingA->id,
            'status' => 'PLACED',
        ]);
        $this->assertDatabaseHas('edu_placements', [
            'student_user_id' => $cand2->id,
            'opening_id' => $openingB->id,
            'status' => 'PLACED',
        ]);

        // Charlie is unmatched because capacity is 1 each and Alice/Bob have higher scores
        $this->assertContains((string) $cand3->id, array_map('strval', $result['unmatched_candidates']));
    }

    public function test_matching_respects_opening_capacity_limits(): void
    {
        $candidates = User::factory()->count(5)->create();
        $this->createProgramWithCerts(
            $candidates->map(fn ($u, $idx) => ['user' => $u, 'score' => 90 - $idx * 2])->all(),
            'PRG-DEV-01'
        );

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-DEV-CAP-2',
            'hiring_entity' => 'TechHub',
            'title' => 'Software Developer',
            'capacity' => 2,
            'annual_salary_minor' => 180_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-DEV-01',
            'status' => 'OPEN',
        ]);

        foreach ($candidates as $cand) {
            EduTalentPreference::create([
                'id' => (string) Str::uuid(),
                'student_user_id' => $cand->id,
                'opening_id' => $opening->id,
                'rank_order' => 1,
            ]);
        }

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-DEV-01']);

        $placements = EduPlacement::where('opening_id', $opening->id)->get();
        $this->assertCount(2, $placements);
        $this->assertEquals(0, $result['remaining_capacities'][$opening->id]);
    }

    public function test_matching_solution_has_zero_blocking_pairs_via_verifier(): void
    {
        $cands = User::factory()->count(4)->create();
        $this->createProgramWithCerts([
            ['user' => $cands[0], 'score' => 95],
            ['user' => $cands[1], 'score' => 88],
            ['user' => $cands[2], 'score' => 82],
            ['user' => $cands[3], 'score' => 76],
        ], 'PRG-NET-01');

        $op1 = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-NET-1',
            'hiring_entity' => 'NetCorp',
            'title' => 'Net Engineer 1',
            'capacity' => 1,
            'annual_salary_minor' => 150_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-NET-01',
            'status' => 'OPEN',
        ]);
        $op2 = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-NET-2',
            'hiring_entity' => 'CloudCorp',
            'title' => 'Net Engineer 2',
            'capacity' => 2,
            'annual_salary_minor' => 160_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-NET-01',
            'status' => 'OPEN',
        ]);

        // Express preferences
        foreach ($cands as $cand) {
            EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $op1->id, 'rank_order' => 1]);
            EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $op2->id, 'rank_order' => 2]);
        }

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-NET-01']);

        $this->assertArrayHasKey('certificate', $result);
        $this->assertArrayHasKey('raw_matching', $result);

        // Explicitly verify via BlockingPairVerifier
        $isValid = BlockingPairVerifier::verify(
            $result['raw_matching'],
            $result['candidate_preferences'],
            $result['organization_data']
        );
        $this->assertTrue($isValid, 'Stable matching must have zero blocking pairs');
    }

    public function test_ineligible_candidate_without_required_credential_is_excluded(): void
    {
        $qualifiedUser = User::factory()->create();
        $unqualifiedUser = User::factory()->create();

        $this->createProgramWithCerts([
            ['user' => $qualifiedUser, 'score' => 90],
        ], 'PRG-SEC-REQ');

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-SEC-ONLY',
            'hiring_entity' => 'SecureBank',
            'title' => 'Security Architect',
            'capacity' => 1,
            'annual_salary_minor' => 300_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-SEC-REQ',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $qualifiedUser->id, 'opening_id' => $opening->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $unqualifiedUser->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-SEC-REQ']);

        $this->assertCount(1, $result['placements']);
        $this->assertEquals($qualifiedUser->id, $result['placements'][0]->student_user_id);
        $this->assertDatabaseMissing('edu_placements', ['student_user_id' => $unqualifiedUser->id]);
    }

    public function test_matching_is_deterministic_and_tie_break_stable(): void
    {
        $cand1 = User::factory()->create(['name' => 'IdenticalA']);
        $cand2 = User::factory()->create(['name' => 'IdenticalB']);

        // Identical scores
        $this->createProgramWithCerts([
            ['user' => $cand1, 'score' => 85],
            ['user' => $cand2, 'score' => 85],
        ], 'PRG-TIE-01');

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-TIE-CAP1',
            'hiring_entity' => 'StrictTieCorp',
            'title' => 'Analyst',
            'capacity' => 1,
            'annual_salary_minor' => 120_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-TIE-01',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand1->id, 'opening_id' => $opening->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand2->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $result1 = $action->simulateMatching(['PRG-TIE-01']);
        $result2 = $action->simulateMatching(['PRG-TIE-01']);

        $this->assertEquals($result1['matches'], $result2['matches'], 'Matching must be perfectly deterministic');
    }

    public function test_placement_records_have_idempotency_key_and_placed_status(): void
    {
        $cand = User::factory()->create();
        $this->createProgramWithCerts([['user' => $cand, 'score' => 90]], 'PRG-IDEMP-01');

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-IDEMP',
            'hiring_entity' => 'IdempCorp',
            'title' => 'Specialist',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-IDEMP-01',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $action->execute(['PRG-IDEMP-01']);

        $placement = EduPlacement::where('student_user_id', $cand->id)->firstOrFail();
        $this->assertEquals('PLACED', $placement->status);
        $this->assertEquals(90, $placement->guarantee_days);
        $this->assertNotEmpty($placement->idempotency_key);
        $this->assertStringStartsWith('edu:placement:', $placement->idempotency_key);
    }

    public function test_empty_candidates_or_openings_returns_empty_matching_payload(): void
    {
        $action = app(MatchTalentPoolAction::class);

        // No openings or candidates for nonexistent program code
        $result = $action->execute(['PRG-EMPTY-NONE']);

        $this->assertSame([], $result['placements']);
        $this->assertSame([], $result['matches']);
        $this->assertArrayHasKey('raw_matching', $result);
        $this->assertSame([], $result['raw_matching']['matches']);
    }

    public function test_candidate_multi_preference_ranking_assigns_first_choice(): void
    {
        $cand = User::factory()->create();
        $this->createProgramWithCerts([['user' => $cand, 'score' => 85]], 'PRG-RANK-01');

        $opening1 = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-RANK-01',
            'hiring_entity' => 'RankCorp1',
            'title' => 'Dev 1',
            'capacity' => 1,
            'annual_salary_minor' => 80_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-RANK-01',
            'status' => 'OPEN',
        ]);

        $opening2 = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-RANK-02',
            'hiring_entity' => 'RankCorp2',
            'title' => 'Dev 2',
            'capacity' => 1,
            'annual_salary_minor' => 90_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-RANK-01',
            'status' => 'OPEN',
        ]);

        // Preference 1: opening1, Preference 2: opening2
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening1->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening2->id, 'rank_order' => 2]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-RANK-01']);

        // Since opening1 has capacity, candidate proposing GS assigns first choice (opening1)
        $this->assertCount(1, $result['placements']);
        $this->assertEquals($opening1->id, $result['placements'][0]->opening_id);
    }

    public function test_matching_tie_breaking_order_by_score_issued_at_and_user_id(): void
    {
        // Cand 1: lower user_id, score 90, issued yesterday
        $cand1 = User::factory()->create();
        // Cand 2: higher user_id, score 90, issued today
        $cand2 = User::factory()->create();

        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-TIE-01',
            'title' => 'Tie Break Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-TIE-01',
            'program_id' => $prog->id,
            'instructor_party_id' => 'ins',
            'max_capacity' => 10,
            'enrolled_count' => 2,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $enr1 = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-T1', 'student_user_id' => $cand1->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $att1 = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-T1', 'student_user_id' => $cand1->id, 'enrollment_id' => $enr1->id, 'score' => 90, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'p1'), 'status' => 'COMPLETED']);

        $enr2 = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-T2', 'student_user_id' => $cand2->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $att2 = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-T2', 'student_user_id' => $cand2->id, 'enrollment_id' => $enr2->id, 'score' => 90, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'p2'), 'status' => 'COMPLETED']);

        $signer = app(CertificateQrSigner::class);
        $c1Uuid = (string) Str::uuid();
        EduCertificate::create([
            'id' => $c1Uuid,
            'certificate_number' => 'CERT-TIE-01',
            'program_id' => $prog->id,
            'student_user_id' => $cand1->id,
            'exam_attempt_id' => $att1->id,
            'issued_at' => now()->subDay(), // Earlier issued_at
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 't1'),
            'qr_token' => $signer->generateToken($c1Uuid, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $c2Uuid = (string) Str::uuid();
        EduCertificate::create([
            'id' => $c2Uuid,
            'certificate_number' => 'CERT-TIE-02',
            'program_id' => $prog->id,
            'student_user_id' => $cand2->id,
            'exam_attempt_id' => $att2->id,
            'issued_at' => now(), // Later issued_at
            'expires_at' => now()->addMonths(24),
            'prev_hash' => hash('sha256', 't1'),
            'certificate_hash' => hash('sha256', 't2'),
            'qr_token' => $signer->generateToken($c2Uuid, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // Capacity 1 opening: both candidates want this opening
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-TIE-01',
            'hiring_entity' => 'TieCorp',
            'title' => 'Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-TIE-01',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand1->id, 'opening_id' => $opening->id, 'rank_order' => 1]);
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand2->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-TIE-01']);

        // Earlier issued_at (cand1) must win the tie-break
        $this->assertCount(1, $result['placements']);
        $this->assertEquals((string) $cand1->id, (string) $result['placements'][0]->student_user_id);
    }

    public function test_simulate_matching_runs_without_persisting_placements(): void
    {
        $cand = User::factory()->create();
        $this->createProgramWithCerts([['user' => $cand, 'score' => 90]], 'PRG-SIMULATE-01');

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-SIMULATE',
            'hiring_entity' => 'SimCorp',
            'title' => 'Analyst',
            'capacity' => 1,
            'annual_salary_minor' => 70_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-SIMULATE-01',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);

        // 1. Valid simulation
        $simResult = $action->simulateMatching(['PRG-SIMULATE-01']);
        $this->assertArrayHasKey('matches', $simResult);
        $this->assertCount(1, $simResult['matches'][$opening->id]);
        $this->assertEquals(0, EduPlacement::where('opening_id', $opening->id)->count(), 'simulateMatching must not persist placements');

        // 2. Empty simulation
        $emptySim = $action->simulateMatching(['PRG-NONEXIST-CODE']);
        $this->assertSame([], $emptySim['matches']);
        $this->assertSame([], $emptySim['unmatched_candidates']);
        $this->assertSame([], $emptySim['remaining_capacities']);
    }

    public function test_matching_recognizes_candidate_via_program_code_fallback(): void
    {
        $cand = User::factory()->create();
        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-FALLBACK-01',
            'title' => 'Fallback Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-FB-01',
            'program_id' => $prog->id,
            'instructor_party_id' => 'ins',
            'max_capacity' => 10,
            'enrolled_count' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $enr = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-FB-01', 'student_user_id' => $cand->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $att = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-FB-01', 'student_user_id' => $cand->id, 'enrollment_id' => $enr->id, 'score' => 88, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'fb'), 'status' => 'COMPLETED']);

        $signer = app(CertificateQrSigner::class);
        $cUuid = (string) Str::uuid();
        // skill_competency_code is 'different_custom_code', but program_code is 'PRG-FALLBACK-01'
        EduCertificate::create([
            'id' => $cUuid,
            'certificate_number' => 'CERT-FB-01',
            'program_id' => $prog->id,
            'student_user_id' => $cand->id,
            'exam_attempt_id' => $att->id,
            'skill_competency_code' => 'different_custom_code',
            'issued_at' => now()->subDay(),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'fb-cert'),
            'qr_token' => $signer->generateToken($cUuid, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-FB-01',
            'hiring_entity' => 'FallbackCorp',
            'title' => 'Operator',
            'capacity' => 1,
            'annual_salary_minor' => 75_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-FALLBACK-01',
            'status' => 'OPEN',
        ]);

        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-FALLBACK-01']);

        // Candidate must be recognized via program_code fallback
        $this->assertCount(1, $result['placements']);
        $this->assertEquals((string) $cand->id, (string) $result['placements'][0]->student_user_id);

        // Verify placement fields
        $plc = $result['placements'][0];
        $expectedId = sprintf('00000000-0008-4000-8000-%012s', substr(hash('sha256', "{$opening->id}:{$cand->id}"), 0, 12));
        $expectedCode = 'PLC-'.strtoupper(substr(hash('sha256', "{$opening->id}:{$cand->id}"), 0, 10));
        $this->assertSame($expectedId, $plc->id);
        $this->assertSame($expectedCode, $plc->placement_code);
        $this->assertEquals(90, $plc->guarantee_days);
        $this->assertSame('PLACED', $plc->status);
        $this->assertSame("edu:placement:{$opening->id}:{$cand->id}", $plc->idempotency_key);
        $this->assertEquals(now()->toDateString(), $plc->placed_at->toDateString());
        $this->assertArrayHasKey('candidate_preferences', $result);
        $this->assertArrayHasKey('organization_data', $result);
        $this->assertArrayHasKey('unmatched_candidates', $result);
        $this->assertArrayHasKey('remaining_capacities', $result);
        $this->assertEquals([$cand->id], $result['matches'][$opening->id]);
        $this->assertIsArray($result['certificate']);
    }

    public function test_empty_candidates_or_organizations_edge_cases_and_structures(): void
    {
        $action = app(MatchTalentPoolAction::class);

        // Case 1: Openings exist in DB, but NO candidates are eligible
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-EMPTY-CAND-01',
            'hiring_entity' => 'NoCandCorp',
            'title' => 'Specialist',
            'capacity' => 2,
            'annual_salary_minor' => 80_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-EMPTY-CAND-01',
            'status' => 'OPEN',
        ]);

        $res1 = $action->execute(['PRG-EMPTY-CAND-01']);
        $this->assertSame([], $res1['placements']);
        $this->assertSame([], $res1['matches']);
        $this->assertSame([], $res1['unmatched_candidates']);
        $this->assertSame([$opening->id => 2], $res1['remaining_capacities']);
        $this->assertSame([], $res1['certificate']);
        $this->assertSame([], $res1['candidate_preferences']);
        $this->assertSame(2, $res1['organization_data'][$opening->id]['capacity']);
        $this->assertSame([], $res1['organization_data'][$opening->id]['preferences']);
        $this->assertSame([], $res1['raw_matching']['matches']);
        $this->assertSame([], $res1['raw_matching']['candidate_matches']);
        $this->assertSame([], $res1['raw_matching']['unmatched_candidates']);
        $this->assertSame([$opening->id => 2], $res1['raw_matching']['remaining_capacities']);

        // simulateMatching with only organizations
        $sim1 = $action->simulateMatching(['PRG-EMPTY-CAND-01']);
        $this->assertSame([], $sim1['matches']);
        $this->assertSame([], $sim1['unmatched_candidates']);
        $this->assertSame([], $sim1['remaining_capacities']);

        // Case 2: Candidates exist with active certificates, but NO openings exist
        $cand = User::factory()->create();
        $this->createProgramWithCerts([['user' => $cand, 'score' => 90]], 'PRG-NO-ORG-01');

        $res2 = $action->execute(['PRG-NO-ORG-01']);
        $this->assertSame([], $res2['placements']);
        $this->assertSame([], $res2['matches']);
        $this->assertSame([], $res2['unmatched_candidates']);
        $this->assertSame([], $res2['remaining_capacities']);
        $this->assertSame([], $res2['certificate']);
        $this->assertSame([], $res2['candidate_preferences']);
        $this->assertSame([], $res2['organization_data']);

        $sim2 = $action->simulateMatching(['PRG-NO-ORG-01']);
        $this->assertSame([], $sim2['matches']);
        $this->assertSame([], $sim2['unmatched_candidates']);
        $this->assertSame([], $sim2['remaining_capacities']);
    }

    public function test_candidate_score_default_and_ordering_tie_breaking_properties(): void
    {
        // 5 candidates with different score / issued_at / user_id
        $candHigh = User::factory()->create(['name' => 'High']);
        $candLow = User::factory()->create(['name' => 'Low']);
        $candDefEarlier = User::factory()->create(['name' => 'DefEarlier']);
        $candDefLater = User::factory()->create(['name' => 'DefLater']);
        $candDefSameTime = User::factory()->create(['name' => 'DefSameTime']);

        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-RANK-01',
            'title' => 'Ranking Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-RANK-01',
            'program_id' => $prog->id,
            'instructor_party_id' => 'ins',
            'max_capacity' => 10,
            'enrolled_count' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $signer = app(CertificateQrSigner::class);

        // High: score 90
        $enrHigh = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-H', 'student_user_id' => $candHigh->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $attHigh = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-H', 'student_user_id' => $candHigh->id, 'enrollment_id' => $enrHigh->id, 'score' => 90, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'h'), 'status' => 'COMPLETED']);
        $cUuidH = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuidH,
            'certificate_number' => 'CERT-H',
            'program_id' => $prog->id,
            'student_user_id' => $candHigh->id,
            'exam_attempt_id' => $attHigh->id,
            'skill_competency_code' => 'PRG-RANK-01',
            'issued_at' => now()->subDays(5),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'h'),
            'qr_token' => $signer->generateToken($cUuidH, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // Low: score 60
        $enrLow = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-L', 'student_user_id' => $candLow->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $attLow = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-L', 'student_user_id' => $candLow->id, 'enrollment_id' => $enrLow->id, 'score' => 60, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'l'), 'status' => 'COMPLETED']);
        $cUuidL = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuidL,
            'certificate_number' => 'CERT-L',
            'program_id' => $prog->id,
            'student_user_id' => $candLow->id,
            'exam_attempt_id' => $attLow->id,
            'skill_competency_code' => 'PRG-RANK-01',
            'issued_at' => now()->subDays(5),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'l'),
            'qr_token' => $signer->generateToken($cUuidL, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // DefEarlier: no exam attempt -> default score 75, issued 10 days ago
        $cUuidDE = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuidDE,
            'certificate_number' => 'CERT-DE',
            'program_id' => $prog->id,
            'student_user_id' => $candDefEarlier->id,
            'exam_attempt_id' => null,
            'skill_competency_code' => 'PRG-RANK-01',
            'issued_at' => now()->subDays(10),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'de'),
            'qr_token' => $signer->generateToken($cUuidDE, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // DefLater: valid exam attempt with score 75, issued 2 days ago
        $enrDL = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-DL', 'student_user_id' => $candDefLater->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $attDL = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-DL', 'student_user_id' => $candDefLater->id, 'enrollment_id' => $enrDL->id, 'score' => 75, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'dl'), 'status' => 'COMPLETED']);
        $cUuidDL = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuidDL,
            'certificate_number' => 'CERT-DL',
            'program_id' => $prog->id,
            'student_user_id' => $candDefLater->id,
            'exam_attempt_id' => $attDL->id,
            'skill_competency_code' => 'PRG-RANK-01',
            'issued_at' => now()->subDays(2),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'dl'),
            'qr_token' => $signer->generateToken($cUuidDL, 'v1'),
            'status' => 'ACTIVE',
        ]);

        // DefSameTime: no exam attempt -> default score 75, issued 10 days ago (same as DefEarlier)
        $cUuidDST = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuidDST,
            'certificate_number' => 'CERT-DST',
            'program_id' => $prog->id,
            'student_user_id' => $candDefSameTime->id,
            'exam_attempt_id' => null,
            'skill_competency_code' => 'PRG-RANK-01',
            'issued_at' => now()->subDays(10),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'dst'),
            'qr_token' => $signer->generateToken($cUuidDST, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-RANK-01',
            'hiring_entity' => 'RankCorp',
            'title' => 'Ranked Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-RANK-01',
            'status' => 'OPEN',
        ]);

        // All candidates register preference for this opening
        foreach ([$candHigh, $candLow, $candDefEarlier, $candDefLater, $candDefSameTime] as $cand) {
            EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $cand->id, 'opening_id' => $opening->id, 'rank_order' => 1]);
        }

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-RANK-01']);

        $orgPrefs = $result['organization_data'][$opening->id]['preferences'];

        // High (score 90) must be 1st
        $this->assertSame((string) $candHigh->id, $orgPrefs[0]);

        // Low (score 60) must be last (index 4)
        $this->assertSame((string) $candLow->id, $orgPrefs[4]);

        // Indices 1, 2, 3 must be candDefEarlier, candDefSameTime, and candDefLater
        // Between issued 10 days ago vs issued 2 days ago: 10 days ago (earlier timestamp) is preferred
        // Between DefEarlier and DefSameTime (both 10 days ago): tie broken by smaller user_id
        $orderedTied = [$candDefEarlier, $candDefSameTime];
        usort($orderedTied, fn ($a, $b) => $a->id <=> $b->id);

        $this->assertSame((string) $orderedTied[0]->id, $orgPrefs[1]);
        $this->assertSame((string) $orderedTied[1]->id, $orgPrefs[2]);
        $this->assertSame((string) $candDefLater->id, $orgPrefs[3]);
    }

    public function test_ineligible_candidate_preference_filtered_and_ksort_determinism(): void
    {
        $candEligible = User::factory()->create();
        $candIneligible = User::factory()->create();

        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-FILTER-01',
            'title' => 'Filter Program',
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-FIL-01',
            'program_id' => $prog->id,
            'instructor_party_id' => 'ins',
            'max_capacity' => 10,
            'enrolled_count' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $enr = EduEnrollment::create(['id' => (string) Str::uuid(), 'enrollment_code' => 'ENR-FIL', 'student_user_id' => $candEligible->id, 'cohort_id' => $cohort->id, 'amount_paid_minor' => 5_000_000, 'status' => 'GRADUATED']);
        $att = EduExamAttempt::create(['id' => (string) Str::uuid(), 'attempt_code' => 'ATT-FIL', 'student_user_id' => $candEligible->id, 'enrollment_id' => $enr->id, 'score' => 85, 'passing_score' => 75, 'is_passed' => true, 'proctor_verification_hash' => hash('sha256', 'fil'), 'status' => 'COMPLETED']);

        $signer = app(CertificateQrSigner::class);
        $cUuid = (string) Str::uuid();
        EduCertificate::create([
            'id' => $cUuid,
            'certificate_number' => 'CERT-FIL',
            'program_id' => $prog->id,
            'student_user_id' => $candEligible->id,
            'exam_attempt_id' => $att->id,
            'skill_competency_code' => 'PRG-FILTER-01',
            'issued_at' => now()->subDay(),
            'expires_at' => now()->addMonths(24),
            'prev_hash' => str_repeat('0', 64),
            'certificate_hash' => hash('sha256', 'fil'),
            'qr_token' => $signer->generateToken($cUuid, 'v1'),
            'status' => 'ACTIVE',
        ]);

        $openingA = EduJobOpening::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'opening_code' => 'JOB-FILTER-A',
            'hiring_entity' => 'CorpA',
            'title' => 'Dev A',
            'capacity' => 1,
            'annual_salary_minor' => 90_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-FILTER-01',
            'status' => 'OPEN',
        ]);

        $openingB = EduJobOpening::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'opening_code' => 'JOB-FILTER-B',
            'hiring_entity' => 'CorpB',
            'title' => 'Dev B',
            'capacity' => 1,
            'annual_salary_minor' => 90_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-FILTER-01',
            'status' => 'OPEN',
        ]);

        // Eligible cand submits preference for A
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $candEligible->id, 'opening_id' => $openingA->id, 'rank_order' => 1]);

        // Ineligible cand has NO certificate for PRG-FILTER-01, but submits preference for Opening A
        EduTalentPreference::create(['id' => (string) Str::uuid(), 'student_user_id' => $candIneligible->id, 'opening_id' => $openingA->id, 'rank_order' => 1]);

        $action = app(MatchTalentPoolAction::class);
        $result = $action->execute(['PRG-FILTER-01']);

        // Ineligible candidate must NOT be in candidate_preferences
        $this->assertArrayNotHasKey((string) $candIneligible->id, $result['candidate_preferences']);
        $this->assertArrayHasKey((string) $candEligible->id, $result['candidate_preferences']);
        $this->assertSame([$openingA->id], $result['candidate_preferences'][(string) $candEligible->id]);

        // Check ksort on organizations: openingA ('...001') before openingB ('...002')
        $this->assertSame([$openingA->id, $openingB->id], array_keys($result['organization_data']));
    }

    private function createProgramWithCerts(array $candidates, string $code): EduProgram
    {
        $prog = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => $code,
            'title' => 'Program '.$code,
            'industry_sector' => 'IT',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 75,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-'.strtoupper(Str::random(6)),
            'program_id' => $prog->id,
            'instructor_party_id' => 'INS-01',
            'max_capacity' => 50,
            'enrolled_count' => count($candidates),
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'status' => 'COMPLETED',
        ]);

        $signer = app(CertificateQrSigner::class);

        foreach ($candidates as $item) {
            $user = $item['user'];
            $score = $item['score'];

            $enr = EduEnrollment::create([
                'id' => (string) Str::uuid(),
                'enrollment_code' => 'ENR-'.strtoupper(Str::random(6)),
                'student_user_id' => $user->id,
                'cohort_id' => $cohort->id,
                'amount_paid_minor' => 5_000_000,
                'status' => 'GRADUATED',
            ]);

            $att = EduExamAttempt::create([
                'id' => (string) Str::uuid(),
                'attempt_code' => 'ATT-'.strtoupper(Str::random(6)),
                'student_user_id' => $user->id,
                'enrollment_id' => $enr->id,
                'score' => $score,
                'passing_score' => 75,
                'is_passed' => true,
                'proctor_verification_hash' => hash('sha256', "proctor-{$user->id}"),
                'status' => 'COMPLETED',
            ]);

            $certUuid = (string) Str::uuid();
            EduCertificate::create([
                'id' => $certUuid,
                'certificate_number' => 'CERT-2026-'.$code.'-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'program_id' => $prog->id,
                'student_user_id' => $user->id,
                'exam_attempt_id' => $att->id,
                'issued_at' => Carbon::now()->subDays(5),
                'expires_at' => Carbon::now()->addMonths(24),
                'prev_hash' => str_repeat('0', 64),
                'certificate_hash' => hash('sha256', $certUuid),
                'qr_token' => $signer->generateToken($certUuid, 'v1'),
                'status' => 'ACTIVE',
            ]);
        }

        return $prog;
    }
}
