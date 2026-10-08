<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\IdentityTenancyService;
use Tests\TestCase;

/**
 * Fase 188 — Integrasi 30 Lini C: Identity, Access & Tenancy Tests
 *
 * Covers:
 *  (a) RBAC + ABAC scope violation returns 403 / false
 *  (b) customer identity graph consent revocation takes effect immediately
 *  (c) consolidated vendor credit exposure sums across lines and flags breach
 *  (d) securitytenancy:audit = 0 discrepancy
 */
class IdentityTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected IdentityTenancyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IdentityTenancyService::class);
    }

    /**
     * (a) RBAC + ABAC policy evaluator enforces tenant & regional scope.
     */
    public function test_rbac_abac_scope_authorization(): void
    {
        // Define policy: REGIONAL_AUDITOR role has READ permission strictly for TENANT_A in ID-JKT
        $this->service->definePolicy('REGIONAL_AUDITOR', 'READ', 'TENANT_A', 'ID-JKT');

        // Valid request -> Authorized
        $this->assertTrue($this->service->authorizeRequest('REGIONAL_AUDITOR', 'READ', 'TENANT_A', 'ID-JKT'));

        // Cross-tenant breach attempt (TENANT_B) -> Rejected
        $this->assertFalse($this->service->authorizeRequest('REGIONAL_AUDITOR', 'READ', 'TENANT_B', 'ID-JKT'));

        // Cross-region breach attempt (ID-SUB) -> Rejected
        $this->assertFalse($this->service->authorizeRequest('REGIONAL_AUDITOR', 'READ', 'TENANT_A', 'ID-SUB'));
    }

    /**
     * (b) Customer consent linkage and immediate revocation.
     */
    public function test_customer_consent_linkage_and_revocation(): void
    {
        $link = $this->service->linkCustomerConsent(7001, 'HOSPITALITY', 'HEALTHCARE', true);
        $this->assertTrue((bool) $link->consent_granted);
        $this->assertNull($link->consent_revoked_at);

        // Revoke consent
        $revoked = $this->service->revokeCustomerConsent($link->link_code);
        $this->assertFalse((bool) $revoked->consent_granted);
        $this->assertNotNull($revoked->consent_revoked_at);
    }

    /**
     * (c) Vendor multi-line consolidated credit exposure limit.
     */
    public function test_vendor_group_credit_exposure(): void
    {
        // Limit: Rp 1,000,000,000
        // Line 1: Exposure Rp 600,000,000
        $exp1 = $this->service->recordVendorExposure('VND-STEEL-01', 1000000000.0, 600000000.0);
        $this->assertEquals(600000000.00, (float) $exp1->current_consolidated_exposure_idr);
        $this->assertFalse((bool) $exp1->is_limit_breached);

        // Line 2: Additional exposure Rp 500,000,000 (total Rp 1.1B > 1.0B) -> Breached
        $exp2 = $this->service->recordVendorExposure('VND-STEEL-01', 1000000000.0, 500000000.0);
        $this->assertEquals(1100000000.00, (float) $exp2->current_consolidated_exposure_idr);
        $this->assertTrue((bool) $exp2->is_limit_breached);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_identity_tenancy_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
