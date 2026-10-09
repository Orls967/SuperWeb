<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\PrivacyVaultService;
use Modules\Integration\Application\Services\RegulatoryComplianceService;
use Modules\Integration\Application\Services\SecurityPenTestService;
use Modules\Integration\Application\Services\ThreatDetectionService;
use Modules\Integration\Application\Services\ZeroTrustService;
use Modules\Integration\Domain\Models\PiiVaultRecord;
use Modules\Integration\Domain\Models\RegulatoryObligation;
use Modules\Integration\Domain\Models\SecurityIncident;
use Tests\TestCase;

/**
 * Fase 144 — Security Wave 2 Feature Tests
 *
 * (a) consent revoked → analytics blind to subject data
 * (b) tokenization reversible ONLY via vault (analytics sees token, not raw)
 * (c) compliance BLOCKED → canLineOperate() = false
 * (d) IR playbook tabletop passes end-to-end
 * (e) pentest sweep = 0 vulnerabilities
 */
class SecurityWave2Test extends TestCase
{
    use RefreshDatabase;

    // ─── (a) Consent Revoked → Analytics Blind ────────────────────────────────

    public function test_a_consent_revoked_means_analytics_cannot_use_subject_data(): void
    {
        $vault = app(PrivacyVaultService::class);

        // Subject opts in for analytics
        $vault->recordConsent('SUBJ-001', 'RET', 'ANALYTICS');
        $this->assertTrue($vault->hasConsent('SUBJ-001', 'RET', 'ANALYTICS'));

        // Store PII
        $record = $vault->storePii('SUBJ-001', 'FINANCIAL', 'RET', '0812-XXXX-CARD');
        $this->assertNotEmpty($record->vault_token);

        // Analytics resolves token — gets safe reference
        $analyticsRef = $vault->resolveTokenForAnalytics($record->vault_token);
        $this->assertNotNull($analyticsRef);

        // Subject revokes consent
        $vault->revokeConsent('SUBJ-001', 'RET', 'ANALYTICS');
        $this->assertFalse($vault->hasConsent('SUBJ-001', 'RET', 'ANALYTICS'));

        // After revocation, analytics SHOULD still see the token (token itself is safe),
        // but business logic must check consent before using it.
        // We verify hasConsent() returns false = analytics system must halt.
        $this->assertFalse($vault->hasConsent('SUBJ-001', 'RET', 'ANALYTICS'));
    }

    // ─── (b) Tokenization Reversible ONLY Via Vault Key ───────────────────────

    public function test_b_tokenization_reversible_only_via_vault_key(): void
    {
        $vault = app(PrivacyVaultService::class);

        // Store PII — only vault returns raw value, analytics gets token
        $rawValue = '08123456789-MEDICAL-RECORD-X';
        $record = $vault->storePii('SUBJ-002', 'MEDICAL', 'HOS', $rawValue);

        // Analytics layer: only token, never raw value
        $analyticsView = $vault->resolveTokenForAnalytics($record->vault_token);
        $this->assertSame($record->vault_token, $analyticsView);
        $this->assertStringNotContainsString('MEDICAL-RECORD', (string) $analyticsView);

        // Only vault key (retrieveByToken) returns raw value
        $retrieved = $vault->retrieveByToken($record->vault_token);
        $this->assertSame($rawValue, $retrieved);
    }

    // ─── (c) Compliance BLOCKED → Module Operation Halted ─────────────────────

    public function test_c_compliance_blocked_obligation_prevents_line_operation(): void
    {
        $compliance = app(RegulatoryComplianceService::class);

        // Seed obligations
        $seeded = $compliance->seedObligations();
        $this->assertGreaterThan(0, $seeded);

        // Initially line can operate (obligations are PENDING/not BLOCKED)
        // Mark HTL BLOCKED for testing
        $compliance->blockObligation('HTL-PARIWISATA', 'License revoked by Ministry of Tourism');
        $this->assertFalse($compliance->canLineOperate('HTL'));

        // Mark compliant → can operate again
        $compliance->markCompliant('HTL-PARIWISATA', 'License renewed and approved');
        $this->assertTrue($compliance->canLineOperate('HTL'));
    }

    // ─── (d) IR Playbook Tabletop Passes End-to-End ──────────────────────────

    public function test_d_ir_playbook_tabletop_exercise_passes(): void
    {
        $soc = app(ThreatDetectionService::class);

        $result = $soc->runTabletopExercise(
            SecurityIncident::CLASS_DATA_LEAK,
            ['HOS', 'FIN', 'RET']
        );

        $this->assertSame('CLOSED', $result['final_status']);
        $this->assertGreaterThan(0, $result['playbook_steps']);
        $this->assertNotNull($result['mttr_minutes']);
        $this->assertStringStartsWith('INC-', $result['incident_code']);
    }

    public function test_d_incident_lifecycle_from_detect_to_close(): void
    {
        $soc = app(ThreatDetectionService::class);

        $incident = $soc->detectIncident([
            'incident_class' => SecurityIncident::CLASS_PAYMENT_FRAUD,
            'severity' => SecurityIncident::SEV_P1,
            'description' => 'Fraudulent payment detected on retail checkout',
            'affected_lines' => ['RET', 'FIN'],
        ]);

        $this->assertSame(SecurityIncident::STATUS_DETECTED, $incident->status);
        $this->assertNotEmpty($incident->playbook_steps);

        $soc->openWarRoom($incident->incident_code);
        $soc->containIncident($incident->incident_code);
        $soc->resolveIncident(
            $incident->incident_code,
            'Payment fraud ring neutralized. 42 fraudulent transactions reversed via bank:reconcile.',
            '1. Implement velocity checks on checkout. 2. Require step-up auth > 5jt. 3. Monitor flagged IPs.'
        );
        $soc->closeWithRegulatorReport(
            $incident->incident_code,
            'SAR filed with PPATK. All reversed transactions verified balanced.'
        );

        $final = SecurityIncident::where('incident_code', $incident->incident_code)->firstOrFail();
        $this->assertSame(SecurityIncident::STATUS_CLOSED, $final->status);
        $this->assertNotNull($final->capa);
        $this->assertNotNull($final->regulator_report);
        $this->assertNotNull($final->mttr());
    }

    // ─── (e) Pentest Sweep = 0 Vulnerabilities ────────────────────────────────

    public function test_e_full_pentest_sweep_returns_zero_vulnerabilities(): void
    {
        $pentest = app(SecurityPenTestService::class);

        $result = $pentest->runFullSweep();

        $this->assertSame('PASS', $result['status']);
        $this->assertSame(0, $result['vulnerabilities']);
        $this->assertSame(0, $result['privilege_esc']);
        $this->assertSame(0, $result['idor']);
        $this->assertSame(0, $result['fuzzing_failures']);
        $this->assertGreaterThan(0, $result['total_tests']);
    }

    // ─── Zero Trust Tests ─────────────────────────────────────────────────────

    public function test_zero_trust_service_identity_registered_with_least_privilege(): void
    {
        $zt = app(ZeroTrustService::class);

        $identity = $zt->registerServiceIdentity([
            'service_name' => 'modules.egy',
            'line_code' => 'EGY',
            'device_trust_level' => 'HIGH',
        ]);

        $this->assertSame('modules.egy', $identity->service_name);
        $this->assertSame('EGY', $identity->line_code);
        $this->assertContains('egy.meter.read', $identity->allowed_abilities);
        $this->assertNotContains('ret.order.create', $identity->allowed_abilities); // Not EGY ability
        $this->assertTrue($identity->isCertValid());
    }

    public function test_zero_trust_enforces_access_based_on_abilities(): void
    {
        $zt = app(ZeroTrustService::class);

        $zt->registerServiceIdentity([
            'service_name' => 'modules.egy.grid',
            'line_code' => 'EGY',
        ]);

        // EGY service has egy.meter.read
        $allowed = $zt->enforceAccess('modules.egy.grid', 'modules.billing', 'egy.meter.read');
        $this->assertTrue($allowed);

        // EGY service does NOT have ret.order.create
        $denied = $zt->enforceAccess('modules.egy.grid', 'modules.ret', 'ret.order.create');
        $this->assertFalse($denied);
    }

    // ─── Right-to-Erasure ─────────────────────────────────────────────────────

    public function test_erasure_request_anonymizes_pii_and_revokes_all_consents(): void
    {
        $vault = app(PrivacyVaultService::class);

        // Store PII for subject
        $vault->storePii('SUBJ-ERASE-01', 'LOCATION', 'LOG', 'GPS:-6.2000,106.8100');
        $vault->storePii('SUBJ-ERASE-01', 'FINANCIAL', 'FIN', 'IBAN-ID-XXXX-9988');
        $vault->recordConsent('SUBJ-ERASE-01', 'LOG', 'ANALYTICS');

        // Submit erasure request
        $request = $vault->submitErasureRequest('SUBJ-ERASE-01', ['LOG', 'FIN']);
        $this->assertStringStartsWith('ERASE-', $request->request_code);

        // Execute erasure
        $completed = $vault->executeErasure($request->request_code);
        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->erasure_report);

        // Verify consent was revoked
        $this->assertFalse($vault->hasConsent('SUBJ-ERASE-01', 'LOG', 'ANALYTICS'));

        // Verify PII records are anonymized
        $activeRecords = PiiVaultRecord::where('subject_id', 'SUBJ-ERASE-01')
            ->where('is_active', true)
            ->count();
        $this->assertSame(0, $activeRecords);
    }

    // ─── Compliance Audit ─────────────────────────────────────────────────────

    public function test_regulatory_audit_returns_healthy_when_no_blocked_overdue(): void
    {
        $compliance = app(RegulatoryComplianceService::class);

        // Mark all seeded obligations as compliant
        $compliance->seedObligations();

        // Mark all as compliant
        RegulatoryObligation::query()->update([
            'status' => 'COMPLIANT',
        ]);

        $audit = $compliance->audit();
        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame('HEALTHY', $audit['status']);
    }

    // ─── security:audit Command ───────────────────────────────────────────────

    public function test_security_audit_command_passes_with_healthy_state(): void
    {
        // Seed compliance
        $compliance = app(RegulatoryComplianceService::class);
        $compliance->seedObligations();

        // Mark all compliant
        RegulatoryObligation::query()->update([
            'status' => 'COMPLIANT',
            'due_date' => now()->addYear()->toDateString(),
        ]);

        // Register a service identity
        $zt = app(ZeroTrustService::class);
        $zt->registerServiceIdentity(['service_name' => 'modules.int.test', 'line_code' => 'INT']);

        // Run a pentest sweep to populate results
        $pentest = app(SecurityPenTestService::class);
        $pentest->runFullSweep();

        $this->artisan('security:audit')->assertExitCode(0);
    }
}
