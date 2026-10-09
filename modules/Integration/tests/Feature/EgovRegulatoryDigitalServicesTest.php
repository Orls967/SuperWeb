<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EgovRegulatoryDigitalServicesService;
use Tests\TestCase;

class EgovRegulatoryDigitalServicesTest extends TestCase
{
    use RefreshDatabase;

    protected EgovRegulatoryDigitalServicesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EgovRegulatoryDigitalServicesService::class);
    }

    public function test_regulatory_submission_idempotency_and_official_receipt_storage(): void
    {
        // 1. First submission (263.1 & 263.7)
        $sub1 = $this->service->submitRegulatoryFiling(
            idempotencyKey: 'TAX-SPT-2026-M09',
            regulatoryDomain: 'TAX',
            templateVersion: 1,
            payloadData: ['vat_payable' => 45000000.0, 'entity_npwp' => '01.234.567.8-001.000']
        );

        $this->assertEquals('ACKNOWLEDGED', $sub1->status);
        $this->assertNotNull($sub1->official_acknowledgement_receipt);
        $this->assertStringStartsWith('RCPT-TAX-', $sub1->official_acknowledgement_receipt);

        // 2. Duplicate submission returns original without creating new row (263.4)
        $sub2 = $this->service->submitRegulatoryFiling(
            idempotencyKey: 'TAX-SPT-2026-M09',
            regulatoryDomain: 'TAX',
            templateVersion: 1,
            payloadData: ['vat_payable' => 45000000.0]
        );

        $this->assertEquals($sub1->id, $sub2->id);
        $this->assertEquals(1, DB::table('egov_regulatory_submissions')->count());
    }

    public function test_regulator_template_evolution_creates_versioned_resubmission(): void
    {
        $v1 = $this->service->submitRegulatoryFiling(
            idempotencyKey: 'ENV-AMDAL-2026-Q1',
            regulatoryDomain: 'ENVIRONMENT_AMDAL',
            templateVersion: 1,
            payloadData: ['co2_tons' => 120.0]
        );

        // Regulator updates form to V2 -> resubmission creates version 2 preserving historical audit (263.5 Edge Case)
        $v2 = $this->service->handleRegulatorTemplateEvolution(
            previousSubmissionCode: $v1->submission_code,
            newTemplateVersion: 2,
            updatedData: ['co2_tons' => 120.0, 'water_discharge_m3' => 450.0]
        );

        $this->assertEquals(2, (int) $v2->template_version);
        $this->assertEquals('ACKNOWLEDGED', $v2->status);
        $this->assertEquals(2, DB::table('egov_regulatory_submissions')->count());
    }

    public function test_license_expiry_enforces_operation_block(): void
    {
        // 1. Active license (263.2)
        $activeLic = $this->service->registerOperatingLicense(
            licenseCode: 'LIC-REFINERY-01',
            businessLine: 'ENERGY',
            permitName: 'Downstream Oil Refining Permit',
            expiryDate: now()->addYears(3)->toDateString()
        );
        $this->assertFalse((bool) $activeLic->operation_blocked);

        // 2. Expired license automatically blocks operation (263.2 & 263.4)
        $expiredLic = $this->service->registerOperatingLicense(
            licenseCode: 'LIC-MINING-IUP-02',
            businessLine: 'MINING',
            permitName: 'Exploration & Production IUP',
            expiryDate: now()->subDays(5)->toDateString()
        );
        $evaluated = $this->service->evaluateLicenseExpiry('LIC-MINING-IUP-02');

        $this->assertTrue((bool) $evaluated->operation_blocked);
        $this->assertEquals('EXPIRED_BLOCKED', $evaluated->renewal_status);
    }

    public function test_public_disclosure_consistency_guard_against_internal_ledger(): void
    {
        // 1. Consistent values match internal ledger (263.3)
        $disclosure = $this->service->publishPublicDisclosure(
            disclosureCode: 'DISC-ESG-GHG-2026',
            disclosureType: 'EMISSIONS_GHG',
            reportingPeriod: '2026-Q3',
            publishedMetricValue: 12500.50,
            internalLedgerVerifiedValue: 12500.50
        );
        $this->assertTrue((bool) $disclosure->is_verified_matching);

        // 2. Mismatch with internal ledger truth is strictly blocked (263.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must match internal ledger truth');
        $this->service->publishPublicDisclosure(
            disclosureCode: 'DISC-FALSE-CLAIM',
            disclosureType: 'EMISSIONS_GHG',
            reportingPeriod: '2026-Q3',
            publishedMetricValue: 5000.00, // Fabricated!
            internalLedgerVerifiedValue: 12500.50
        );
    }

    public function test_submission_network_failure_tracks_retries(): void
    {
        // Simulated gateway failure (263.6)
        $failed = $this->service->submitRegulatoryFiling(
            idempotencyKey: 'LABOR-WLKP-RETRY-01',
            regulatoryDomain: 'LABOR_MANPOWER',
            templateVersion: 1,
            payloadData: ['headcount' => 1500],
            simulateNetworkFailure: true
        );

        $this->assertEquals('FAILED_RETRYING', $failed->status);
        $this->assertEquals(1, (int) $failed->retry_count);
        $this->assertNull($failed->official_acknowledgement_receipt);
    }

    public function test_egov_regulatory_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitRegulatoryFiling('AUD-KEY-1', 'TAX', 1, ['tax' => 100.0]);
        $this->service->registerOperatingLicense('LIC-AUD-1', 'ENERGY', 'Permit', now()->addYear()->toDateString());
        $this->service->publishPublicDisclosure('DISC-AUD', 'CSR', '2026', 100.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: expired license without operation blocked
        DB::table('egov_operating_licenses')->insert([
            'license_code' => 'LIC-UNBLOCKED-EXPIRED',
            'business_line' => 'MINING',
            'permit_name' => 'Expired Permit',
            'expiry_date' => now()->subMonth()->toDateString(),
            'operation_blocked' => false, // Discrepancy!
            'renewal_status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
