<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SmartDistrictService;
use Tests\TestCase;

/**
 * Fase 184 — City Operations, Smart Districts & Public-Private Services Tests
 *
 * Covers:
 *  (a) B2G contract payment strictly requires milestone acceptance
 *  (b) public community reports dashboard completely excludes reporter PII
 *  (c) district:audit = 0 discrepancy
 */
class SmartDistrictTest extends TestCase
{
    use RefreshDatabase;

    protected SmartDistrictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SmartDistrictService::class);
    }

    /**
     * (a) B2G contract payment blocked until milestone accepted.
     */
    public function test_b2g_contract_payment_requires_acceptance(): void
    {
        $contract = $this->service->createB2GContract('DINAS-PEKERJAAN-UMUM', 'Smart IKN District 1', 12000000000.0);

        // 1. Pay before acceptance -> Exception
        try {
            $this->service->disburseContractPayment($contract->contract_code);
            $this->fail('Expected exception for unaccepted B2G payment.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('requires official milestone acceptance', $e->getMessage());
        }

        // 2. Accept milestone -> Payment SUCCESS
        $this->service->acceptMilestone($contract->contract_code);
        $paid = $this->service->disburseContractPayment($contract->contract_code);
        $this->assertTrue((bool) $paid->is_paid);
    }

    /**
     * (b) Public transparency dashboard strips PII (phone number).
     */
    public function test_community_report_public_view_excludes_pii(): void
    {
        $phone = '+6281299988877';
        $report = $this->service->fileCommunityReport(
            'DST-IKN-01',
            'STREETLIGHT',
            '-0.953, 116.711',
            $phone,
            'Lampu jalan mati di bundaran utama'
        );

        $this->assertSame('OPEN', $report->status);

        // Fetch public records
        $publicList = $this->service->getPublicDashboardReports('DST-IKN-01');
        $this->assertNotEmpty($publicList);

        $first = (array) $publicList[0];
        $this->assertArrayNotHasKey('reporter_raw_phone', $first);
        $this->assertStringNotContainsString($phone, json_encode($first));
    }

    /**
     * (c) Audit status healthy with 0 discrepancies.
     */
    public function test_smart_district_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
