<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseIdentityZeroTrustService;
use Tests\TestCase;

class EnterpriseIdentityZeroTrustTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseIdentityZeroTrustService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseIdentityZeroTrustService::class);
    }

    public function test_entitlement_grant_jit_elevation_and_zero_trust_flow(): void
    {
        // 457.1 Grant access entitlement with recertification date
        $ent = $this->service->grantEntitlement(
            userId: 'USR-DEV-001',
            roleCode: 'ROLE_LOGISTICS_OPERATOR',
            lineCode: 'LINE-FREIGHT-01'
        );

        $this->assertEquals('USR-DEV-001', $ent->user_id);
        $this->assertTrue((bool) $ent->is_active);

        // 457.2 JIT Elevation
        $jit = $this->service->requestJitElevation('USR-DEV-001', 'jit_elevation', 30);
        $this->assertEquals('jit_elevation', $jit->elevation_type);
        $this->assertTrue((bool) $jit->post_review_completed);

        // 457.3 Zero-Trust verification: Compliant context passes
        $verified = $this->service->verifyZeroTrustAccess(
            userId: 'USR-DEV-001',
            mfaVerified: true,
            managedDevice: true,
            lowRiskContext: true
        );
        $this->assertTrue($verified);

        // 457.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_zero_trust_rejection_and_break_glass_post_review_edge_cases(): void
    {
        // 457.3 & 457.4 Zero-Trust Policy Decision Point rejects unmanaged device
        try {
            $this->service->verifyZeroTrustAccess(
                userId: 'USR-REMOTE',
                mfaVerified: true,
                managedDevice: false, // Unmanaged BYOD rejected!
                lowRiskContext: true
            );
            $this->fail('Expected exception for zero trust context rejection');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('rejected by Policy Decision Point', $e->getMessage());
        }

        // 457.2 & 457.6 Break-glass requires mandatory post review
        $bg = $this->service->requestJitElevation('USR-ADMIN-EMERGENCY', 'break_glass', 120);
        $this->assertFalse((bool) $bg->post_review_completed);

        // Review completed
        $reviewed = $this->service->reviewBreakGlassSession($bg->session_code);
        $this->assertTrue((bool) $reviewed->post_review_completed);

        // 457.5 Expired entitlement auto-revocation
        $this->service->grantEntitlement('USR-EXPIRED', 'ROLE_GUEST', 'LINE-ALL', false, now()->subDay()->toDateTimeString());
        $lapsedCount = $this->service->expireLapsedEntitlements();
        $this->assertGreaterThan(0, $lapsedCount);
    }
}
