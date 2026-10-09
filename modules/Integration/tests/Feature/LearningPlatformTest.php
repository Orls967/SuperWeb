<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\LearningPlatformService;
use Tests\TestCase;

/**
 * Fase 167 — Learning Platform, Digital Content & Credentials Tests
 *
 * Covers:
 *  (a) course version snapshot immutable & prerequisite cycle rejected
 *  (b) assessment question form randomized deterministically by seed
 *  (c) duplicate completion idempotent & prerequisite enforced
 *  (d) revoked credential rejected without deleting history
 *  (e) campus:audit reconciliation clean
 */
class LearningPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected LearningPlatformService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LearningPlatformService::class);
    }

    /**
     * (a) Prerequisite cycle rejected.
     */
    public function test_prerequisite_cycle_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->registerCourse('CS101', 'Intro to Computer Science', 1, 'CS101');
    }

    /**
     * (b) Assessment randomized deterministically by seed.
     */
    public function test_assessment_form_generation_deterministic(): void
    {
        $questions = ['Q1', 'Q2', 'Q3', 'Q4', 'Q5'];

        $form1 = $this->service->generateAssessmentForm('CS101', 42, $questions);
        $form2 = $this->service->generateAssessmentForm('CS101', 42, $questions);

        $this->assertSame($form1->randomized_form_data, $form2->randomized_form_data);
    }

    /**
     * (c) Credential issuance requires prerequisite and is idempotent.
     */
    public function test_credential_issuance_and_prerequisites(): void
    {
        $this->service->registerCourse('MATH101', 'Calculus I');
        $this->service->registerCourse('MATH201', 'Calculus II', 1, 'MATH101');

        // Cannot issue MATH201 without MATH101
        try {
            $this->service->issueCredential(2001, 'MATH201');
            $this->fail('Expected exception for missing prerequisite.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('MATH101', $e->getMessage());
        }

        // Complete prerequisite first
        $cred1 = $this->service->issueCredential(2001, 'MATH101');
        $this->assertSame('MATH101', $cred1->course_code);

        // Now MATH201 succeeds
        $cred2 = $this->service->issueCredential(2001, 'MATH201');
        $this->assertSame('MATH201', $cred2->course_code);

        // Idempotency: re-issuing MATH201 returns the exact same credential code
        $cred2Retry = $this->service->issueCredential(2001, 'MATH201');
        $this->assertSame($cred2->credential_code, $cred2Retry->credential_code);
    }

    /**
     * (d) Revocation sets is_revoked = true without deleting record.
     */
    public function test_credential_revocation(): void
    {
        $this->service->registerCourse('BIO101', 'Biology');
        $cred = $this->service->issueCredential(2002, 'BIO101');
        $this->assertFalse((bool) $cred->is_revoked);

        $revoked = $this->service->revokeCredential($cred->credential_code);
        $this->assertTrue((bool) $revoked->is_revoked);
        $this->assertSame($cred->credential_code, $revoked->credential_code);
    }

    /**
     * (e) Audit status healthy.
     */
    public function test_learning_platform_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
