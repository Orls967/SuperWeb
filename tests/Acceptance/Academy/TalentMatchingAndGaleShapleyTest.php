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
