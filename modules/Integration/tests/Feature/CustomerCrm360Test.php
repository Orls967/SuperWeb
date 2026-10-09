<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CustomerCrm360Service;
use Tests\TestCase;

/**
 * Fase 219 — Pelanggan: Unified CRM & Customer 360 Tests
 *
 * Covers:
 *  (a) customer golden record creation and reversible merge
 *  (b) unmerge procedure restores child record safely without data loss
 *  (c) consent management gates cross-line data sharing
 *  (d) crm:audit = 0 discrepancy
 */
class CustomerCrm360Test extends TestCase
{
    use RefreshDatabase;

    protected CustomerCrm360Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerCrm360Service::class);
    }

    /**
     * (a) & (b) Customer golden record reversible merge and unmerge.
     */
    public function test_customer_merge_and_reversible_unmerge(): void
    {
        $c1 = $this->service->upsertCustomer('CUST-GOLDEN-01', 'budi@example.com', 'Budi Santoso');
        $c2 = $this->service->upsertCustomer('CUST-DUP-02', '08123456789', 'Budi S.');

        // 1. Merge child into parent
        $parent = $this->service->mergeRecords('CUST-GOLDEN-01', 'CUST-DUP-02');
        $this->assertStringContainsString('CUST-DUP-02', (string) $parent->merged_child_ids);

        $childAfterMerge = DB::table('crm_customer_golden_records')->where('golden_id', 'CUST-DUP-02')->first();
        $this->assertFalse((bool) $childAfterMerge->is_active);

        // 2. Undo merge (unmerge)
        $restoredChild = $this->service->unmergeRecord('CUST-GOLDEN-01', 'CUST-DUP-02');
        $this->assertTrue((bool) $restoredChild->is_active);

        $parentAfterUnmerge = DB::table('crm_customer_golden_records')->where('golden_id', 'CUST-GOLDEN-01')->first();
        $this->assertStringNotContainsString('CUST-DUP-02', (string) $parentAfterUnmerge->merged_child_ids);
    }

    /**
     * (c) Consent management for cross-line data sharing.
     */
    public function test_customer_consent_gating(): void
    {
        $this->service->upsertCustomer('CUST-GOLDEN-01', 'budi@example.com', 'Budi Santoso');

        // Initially no consent
        $this->assertFalse($this->service->isSharingAllowed('CUST-GOLDEN-01', 'CROSS_LINE_DATA_SHARE'));

        // Grant consent
        $this->service->setConsent('CUST-GOLDEN-01', 'CROSS_LINE_DATA_SHARE', true);
        $this->assertTrue($this->service->isSharingAllowed('CUST-GOLDEN-01', 'CROSS_LINE_DATA_SHARE'));

        // Revoke consent
        $this->service->setConsent('CUST-GOLDEN-01', 'CROSS_LINE_DATA_SHARE', false);
        $this->assertFalse($this->service->isSharingAllowed('CUST-GOLDEN-01', 'CROSS_LINE_DATA_SHARE'));
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_crm_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
