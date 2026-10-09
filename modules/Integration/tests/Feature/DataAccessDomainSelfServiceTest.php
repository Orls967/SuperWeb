<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataAccessDomainSelfService;
use Tests\TestCase;

class DataAccessDomainSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DataAccessDomainSelfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataAccessDomainSelfService::class);
    }

    public function test_sensitive_tier_literacy_gate_and_time_bound_expiry(): void
    {
        // 1. Untrained user blocked from sensitive access tier (344.3 & 344.4)
        try {
            $this->service->issueTimeBoundAccessGrant(
                grantCode: 'GRANT-SENSITIVE-UNTRAINED',
                userId: 'USER-INTERN-01',
                domainName: 'PAYROLL',
                accessTier: 'RESTRICTED_SENSITIVE',
                userCertified: false, // Untrained!
                expiresAt: Carbon::now()->addDays(7)
            );
            $this->fail('Expected exception for untrained user accessing sensitive tier');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('User requires certified training to access sensitive domain data', $e->getMessage());
        }

        // 2. Trained user successfully granted sensitive access (344.3)
        $validGrant = $this->service->issueTimeBoundAccessGrant(
            grantCode: 'GRANT-SENSITIVE-CERTIFIED',
            userId: 'USER-SR-ANALYST-01',
            domainName: 'PAYROLL',
            accessTier: 'RESTRICTED_SENSITIVE',
            userCertified: true,
            expiresAt: Carbon::now()->addDays(7)
        );
        $this->assertTrue((bool) $validGrant->user_certified_literacy);
        $this->assertTrue($this->service->isAccessActive('GRANT-SENSITIVE-CERTIFIED'));

        // 3. Time-bound grant expiry is enforced (344.1 & 344.4)
        $expiredGrant = $this->service->issueTimeBoundAccessGrant(
            grantCode: 'GRANT-EXPIRED-TEST',
            userId: 'USER-SR-ANALYST-01',
            domainName: 'FINANCE',
            accessTier: 'GENERAL',
            userCertified: true,
            expiresAt: Carbon::now()->subMinutes(5) // Expired 5 minutes ago!
        );
        $this->assertFalse($this->service->isAccessActive('GRANT-EXPIRED-TEST'));
    }

    public function test_domain_product_template_governance_and_ci_check(): void
    {
        // 1. Template not established by governance council is rejected (344.5 Edge Case)
        try {
            $this->service->publishDomainTemplate(
                templateCode: 'TMPL-ROGUE-01',
                domainName: 'SMELTER_OPERATIONS',
                fromGovernanceCouncil: false, // Rogue template!
                ciCheckPassed: true
            );
            $this->fail('Expected exception for rogue domain template');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Domain product templates must be established by Governance Council', $e->getMessage());
        }

        // 2. Council approved template passing CI publishes successfully (344.2 & 344.4)
        $template = $this->service->publishDomainTemplate(
            templateCode: 'TMPL-MINING-TELEMETRY-V1',
            domainName: 'MINING',
            fromGovernanceCouncil: true,
            ciCheckPassed: true
        );
        $this->assertTrue((bool) $template->established_by_governance_council);
        $this->assertTrue((bool) $template->ci_schema_quality_check_passed);
        $this->assertTrue((bool) $template->published_to_catalog);
    }

    public function test_data_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueTimeBoundAccessGrant('G-AUD', 'U1', 'MINING', 'GENERAL', false, Carbon::now()->addDays(1));
        $this->service->publishDomainTemplate('T-AUD', 'MINING', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: uncertified user active on sensitive tier
        DB::table('domain_data_access_grants')->insert([
            'grant_code' => 'G-DEFECT-UNCERTIFIED',
            'user_id' => 'U2',
            'domain_name' => 'PAYROLL',
            'access_tier' => 'RESTRICTED_SENSITIVE',
            'user_certified_literacy' => false, // Discrepancy!
            'grant_expires_at' => Carbon::now()->addDays(1),
            'access_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
