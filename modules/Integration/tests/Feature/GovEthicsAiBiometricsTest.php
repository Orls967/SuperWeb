<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GovEthicsAiBiometricsService;
use Tests\TestCase;

class GovEthicsAiBiometricsTest extends TestCase
{
    use RefreshDatabase;

    protected GovEthicsAiBiometricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GovEthicsAiBiometricsService::class);
    }

    public function test_sensitive_system_blocked_without_approved_ethics_assessment(): void
    {
        // 1. Submit unapproved assessment (294.1 & 294.5)
        $this->service->submitEthicsAssessment(
            assessmentCode: 'ETHICS-HOSPITAL-FACIAL-01',
            systemName: 'Patient Triage Facial Recognition',
            tier: 'BIOMETRICS',
            necessityJustification: 'Rapid identification of unconscious trauma patients upon arrival.',
            ethicsBoardApproved: false
        );

        // Deployment blocked (294.5)
        $isAuthorized = $this->service->authorizeSystemDeployment('ETHICS-HOSPITAL-FACIAL-01');
        $this->assertFalse($isAuthorized);

        // 2. Necessity test failure: missing justification rejected (294.7)
        try {
            $this->service->submitEthicsAssessment(
                'ETHICS-DEFECT',
                'Defect System',
                'MEDICAL',
                ''
            );
            $this->fail('Expected exception for empty necessity justification');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires documented necessity and proportionality justification', $e->getMessage());
        }
    }

    public function test_biometric_governance_template_protection_and_opt_out(): void
    {
        // 1. Register biometric with template encryption and PIN fallback (294.2 & 294.8)
        $bio = $this->service->registerBiometricIdentity(
            subjectId: 'STAFF-WAREHOUSE-99',
            type: 'FACIAL_SCAN',
            rawBiometricData: 'VECTOR-FACE-ENCODING-XYZ',
            providePinAlternative: true
        );
        $this->assertTrue((bool) $bio->has_alternative_pin_path);
        $this->assertFalse((bool) $bio->is_biometric_revoked);
        $this->assertNotNull($bio->encrypted_template_hash);

        // 2. User opt-out revokes biometric (294.2 & 294.5)
        $revoked = $this->service->revokeBiometricIdentity('STAFF-WAREHOUSE-99');
        $this->assertTrue((bool) $revoked->is_biometric_revoked);
    }

    public function test_child_safeguarding_requires_guardian_consent(): void
    {
        // 1. Under 18 student without guardian consent rejected (294.4 & 294.5)
        try {
            $this->service->registerStudentSafeguard('STUDENT-MINOR-14YO', 14, null);
            $this->fail('Expected exception for minor student without guardian consent');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Student under 18 requires verified parent/guardian consent', $e->getMessage());
        }

        // 2. Minor student with verified consent registered with adult contact blocked (294.4)
        $safeguard = $this->service->registerStudentSafeguard(
            studentId: 'STUDENT-MINOR-14YO',
            age: 14,
            guardianConsentRef: 'CONSENT-PARENT-ID-883'
        );
        $this->assertTrue((bool) $safeguard->targeted_adult_contact_blocked);
        $this->assertTrue((bool) $safeguard->guardian_controls_active);
    }

    public function test_gov_ethics_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitEthicsAssessment('ETH-AUD', 'Sys', 'LOCATION', 'Justification', true);
        $this->service->registerBiometricIdentity('USER-AUD', 'FINGERPRINT', 'VEC', true);
        $this->service->registerStudentSafeguard('STU-AUD', 15, 'CONSENT-1');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: minor student without guardian consent
        DB::table('gov_child_safeguards')->insert([
            'student_user_id' => 'STU-UNPROTECTED',
            'age_years' => 12,
            'parent_guardian_consent_ref' => null, // Discrepancy!
            'targeted_adult_contact_blocked' => true,
            'guardian_controls_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
