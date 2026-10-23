<?php

declare(strict_types=1);

namespace Modules\Edu\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Edu\Application\Actions\IssueVerifiableCredentialBadgeAndSkillMatrixAction;
use Modules\Edu\Application\Actions\MatchTalentPipelinePlacementWithGuaranteeEscrowAction;
use Tests\TestCase;

class Fase135To136EduTalentExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_135_verifiable_credential_issuance_with_gapless_hash_chain(): void
    {
        $action = app(IssueVerifiableCredentialBadgeAndSkillMatrixAction::class);

        $params = [
            'issuance_code' => 'CERT-K3-001',
            'student_id' => 'STU-MINER-101',
            'program_code' => 'PRG-HEAVY-EQUIP-K3',
            'required_prerequisites' => ['BASIC-SAFETY-101', 'FIRST-AID-101'],
            'completed_courses' => ['BASIC-SAFETY-101', 'FIRST-AID-101', 'HAZMAT-101'],
            'exam_score_pct' => 92,
            'passing_grade_pct' => 80,
            'tuition_fee_minor' => 8000000,
        ];

        $res = $action->execute($params);

        $this->assertEquals('CERTIFIED', $res['competency_status']);
        $this->assertEquals(92, $res['exam_score_pct']);
        $this->assertNotEmpty($res['certificate_hash']);
        $this->assertStringStartsWith('QR-CERT-', $res['gapless_qr_token']);

        $tx = LedgerTransaction::where('reference_id', 'CERT-K3-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_135_rejects_enrollment_when_prerequisites_unfulfilled(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Missing required prerequisites');

        $action = app(IssueVerifiableCredentialBadgeAndSkillMatrixAction::class);
        $action->execute([
            'issuance_code' => 'CERT-FAIL-01',
            'student_id' => 'STU-MINER-102',
            'program_code' => 'PRG-HEAVY-EQUIP-K3',
            'required_prerequisites' => ['BASIC-SAFETY-101', 'FIRST-AID-101'],
            'completed_courses' => ['BASIC-SAFETY-101'], // FIRST-AID-101 missing!
        ]);
    }

    public function test_fase_136_deterministic_talent_matching_across_conglomerate_lines(): void
    {
        $action = app(MatchTalentPipelinePlacementWithGuaranteeEscrowAction::class);

        // Candidate skills: [PHP, LARAVEL, DOCKER, KUBERNETES]
        // Required skills: [LARAVEL, DOCKER, POSTGRESQL]
        // Intersection: [LARAVEL, DOCKER] = 2.
        // Union: [PHP, LARAVEL, DOCKER, KUBERNETES, POSTGRESQL] = 5.
        // Score: (2 / 5) * 100 = 40%.
        $params = [
            'placement_code' => 'PLC-MATCH-001',
            'candidate_id' => 'DEV-001',
            'hiring_line' => 'TELECOM',
            'candidate_skills' => ['PHP', 'LARAVEL', 'DOCKER', 'KUBERNETES'],
            'required_skills' => ['LARAVEL', 'DOCKER', 'POSTGRESQL'],
            'contracted_hours' => 160,
            'reported_hours' => 150,
            'first_year_salary_minor' => 300000000,
            'headhunter_fee_rate_pct' => 10,
            'probation_days_passed' => 60, // Warranty is 90 days -> HELD_IN_ESCROW
            'warranty_period_days' => 90,
        ];

        $res1 = $action->execute($params);
        $res2 = $action->execute($params);

        // Deterministic identical results
        $this->assertEquals(40, $res1['match_score_pct']);
        $this->assertEquals($res1['match_score_pct'], $res2['match_score_pct']);
        $this->assertEquals('HELD_IN_ESCROW', $res1['escrow_status']);
    }

    public function test_fase_136_headhunter_escrow_hold_and_contingent_hours_validation(): void
    {
        $action = app(MatchTalentPipelinePlacementWithGuaranteeEscrowAction::class);

        // Scenario 1: Warranty passed (90 >= 90) -> RELEASED_TO_AGENCY
        $params = [
            'placement_code' => 'PLC-RELEASE-002',
            'candidate_id' => 'DEV-002',
            'hiring_line' => 'HEALTHCARE',
            'candidate_skills' => ['HL7', 'FHIR'],
            'required_skills' => ['HL7', 'FHIR'],
            'contracted_hours' => 160,
            'reported_hours' => 160,
            'first_year_salary_minor' => 200000000,
            'headhunter_fee_rate_pct' => 10,
            'probation_days_passed' => 90,
            'warranty_period_days' => 90,
        ];

        $res = $action->execute($params);
        $this->assertEquals('RELEASED_TO_AGENCY', $res['escrow_status']);
        $this->assertEquals(20000000, $res['headhunter_fee_minor']);

        $tx = LedgerTransaction::where('reference_id', 'PLC-RELEASE-002')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));

        // Scenario 2: Reported hours > contracted hours -> exception
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Contingent timesheet violation');

        $action->execute([
            'placement_code' => 'PLC-OVERHOURS',
            'candidate_id' => 'DEV-003',
            'contracted_hours' => 100,
            'reported_hours' => 120, // Violation!
        ]);
    }
}
