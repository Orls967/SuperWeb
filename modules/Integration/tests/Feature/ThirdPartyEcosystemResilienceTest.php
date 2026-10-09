<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\ThirdPartyEcosystemResilienceService;
use Tests\TestCase;

class ThirdPartyEcosystemResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected ThirdPartyEcosystemResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ThirdPartyEcosystemResilienceService::class);
    }

    public function test_vendor_concentration_and_rehearsal_flow(): void
    {
        // 403.1 & 403.4
        $vendor = $this->service->registerVendor(
            vendorCode: 'VND-CLOUD-AWS',
            vendorName: 'AWS Cloud Services',
            category: 'cloud',
            spendPercentage: 35.00,
            concentrationLimit: 40.00,
            alternateVendorCode: 'VND-CLOUD-GCP',
            alternateQualified: true
        );

        $this->assertEquals('VND-CLOUD-AWS', $vendor->vendor_code);
        $this->assertFalse((bool) $vendor->has_emergency_playbook);

        // 403.3 Exit strategy rehearsal
        $rehearsal = $this->service->recordRehearsal(
            rehearsalCode: 'REH-AWS-2026',
            vendorCode: 'VND-CLOUD-AWS',
            drillType: 'exit_strategy',
            recoveryTimeMinutes: 45,
            dataExported: true,
            credentialsRotated: true,
            passed: true
        );

        $this->assertTrue((bool) $rehearsal->passed);

        // Audit clean (403.4)
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_concentration_breach_and_emergency_playbook_edge_case(): void
    {
        // 403.5 Edge case: sudden vendor failure without qualified alternate
        $vendor = $this->service->registerVendor(
            vendorCode: 'VND-PAY-SOLO',
            vendorName: 'Solo Payment Gateway',
            category: 'payment',
            spendPercentage: 55.00, // Breaches 40% limit!
            concentrationLimit: 40.00,
            alternateVendorCode: null,
            alternateQualified: false
        );

        $this->assertTrue((bool) $vendor->has_emergency_playbook);

        // Audit catches discrepancy
        $audit = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $audit['status']);
        $this->assertEquals(1, $audit['limit_breaches']);

        // Qualify alternate vendor (403.6)
        $this->service->qualifyAlternate('VND-PAY-SOLO', 'VND-PAY-BACKUP');
        $updated = DB::table('gov_vendor_concentrations')->where('vendor_code', 'VND-PAY-SOLO')->first();
        $this->assertTrue((bool) $updated->alternate_qualified);
        $this->assertEquals('VND-PAY-BACKUP', $updated->alternate_vendor_code);
    }
}
