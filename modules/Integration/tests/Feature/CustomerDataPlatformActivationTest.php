<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CustomerDataPlatformActivationService;
use Tests\TestCase;

class CustomerDataPlatformActivationTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerDataPlatformActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerDataPlatformActivationService::class);
    }

    public function test_profile_registration_and_activation_with_consent(): void
    {
        // 416.1 Profile with consent
        $profile = $this->service->registerProfile(
            profileCode: 'CUST-PRF-001',
            email: 'john.doe@example.com',
            phone: '+628123456789',
            consent: true,
            suppressed: false,
            segment: 'PREMIUM_VIP'
        );

        $this->assertEquals('CUST-PRF-001', $profile->profile_code);

        // 416.2 Activate profile
        $res = $this->service->activateProfileForCampaign('CUST-PRF-001', 'whatsapp');
        $this->assertEquals('ACTIVATED', $res['status']);
        $this->assertEquals('whatsapp', $res['channel']);

        // 416.3 & 416.4 Identity merge and reversible test
        $merge = $this->service->mergeProfiles(
            mergeCode: 'MRG-2026-001',
            sourceCode: 'CUST-PRF-002',
            targetCode: 'CUST-PRF-001',
            reason: 'Consolidated guest checkout with verified account',
            reviewer: 'CRM Identity Lead',
            manualReviewApproved: true
        );

        $this->assertEquals('MRG-2026-001', $merge->merge_code);
        $this->assertFalse((bool) $merge->is_reversed);

        // Reverse merge
        $reversed = $this->service->reverseMerge('MRG-2026-001');
        $this->assertTrue((bool) $reversed->is_reversed);

        // 416.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_suppressed_or_no_consent_profile_blocked_from_activation(): void
    {
        // Suppressed profile
        $this->service->registerProfile(
            profileCode: 'CUST-SUPPRESSED',
            email: 'optout@example.com',
            phone: '+628111111111',
            consent: true,
            suppressed: true // On suppression list!
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is on the global suppression list');

        $this->service->activateProfileForCampaign('CUST-SUPPRESSED', 'email');
    }
}
