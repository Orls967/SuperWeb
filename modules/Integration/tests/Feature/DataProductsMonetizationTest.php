<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataProductsMonetizationService;
use Tests\TestCase;

class DataProductsMonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected DataProductsMonetizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataProductsMonetizationService::class);
    }

    public function test_publish_and_terminate_data_product(): void
    {
        // 1. Publish data product with cohort gate (244.1)
        $product = $this->service->publishDataProduct(
            productCode: 'DP-MARKET-BENCHMARK',
            productName: 'Cross-Industry Supply Chain Index',
            subscriptionPriceMonthly: 4500.0,
            minCohortSize: 50
        );

        $this->assertEquals('ACTIVE', $product->status);
        $this->assertEquals(50, (int) $product->min_cohort_size);

        // 2. Terminate data product with orderly archival (244.7)
        $terminated = $this->service->terminateDataProduct('DP-MARKET-BENCHMARK', 'Product line deprecated for v2 index');
        $this->assertEquals('TERMINATED', $terminated->status);
    }

    public function test_data_sharing_agreement_field_level_scoping(): void
    {
        // Allowed: sku, price, store_region
        $agreement = $this->service->createSharingAgreement(
            partnerId: 'PARTNER-RETAIL-CORP',
            contractId: 'CTR-2026-88',
            allowedFields: ['sku', 'price', 'store_region']
        );

        $this->assertEquals('ACTIVE', $agreement->status);
        $this->assertTrue((bool) $agreement->consent_active);

        // Request with unauthorized PII field (customer_ssn) (244.2 & 244.5)
        $access = $this->service->accessSharedData(
            agreementCode: $agreement->agreement_code,
            requestedFields: ['sku', 'price', 'customer_ssn']
        );

        $this->assertTrue($access['access_authorized']);
        $this->assertEquals(['sku', 'price'], $access['granted_fields']);
        $this->assertEquals(['customer_ssn'], $access['unauthorized_fields_filtered']);
    }

    public function test_data_sharing_revocation_on_misuse(): void
    {
        $agreement = $this->service->createSharingAgreement(
            partnerId: 'PARTNER-SHADY',
            contractId: 'CTR-2026-99',
            allowedFields: ['aggregated_traffic']
        );

        // Revoke due to contract misuse (244.6 Edge Case)
        $revoked = $this->service->revokeSharingForMisuse(
            agreementCode: $agreement->agreement_code,
            violationReason: 'Partner attempted unapproved secondary data scraping'
        );

        $this->assertEquals('REVOKED', $revoked->status);
        $this->assertFalse((bool) $revoked->consent_active);

        // Attempting to access revoked data must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->service->accessSharedData($agreement->agreement_code, ['aggregated_traffic']);
    }

    public function test_data_clean_room_zero_raw_exposure_and_anti_reidentification(): void
    {
        // 1. Safe computation (cohort = 120 >= 20) (244.3 & 244.5)
        $safeRun = $this->service->executeCleanRoomComputation(
            partyA: 'BANK_ALPHA',
            partyB: 'TELCO_BETA',
            computationType: 'PSI_INTERSECTION',
            outputCohortCount: 120
        );

        $this->assertEquals(0, (int) $safeRun->raw_rows_exposed);
        $this->assertTrue((bool) $safeRun->anti_reidentification_passed);
        $this->assertTrue((bool) $safeRun->approved_for_export);

        // 2. Unsafe tiny cohort (cohort = 4 < 20) risks re-identification
        $unsafeRun = $this->service->executeCleanRoomComputation(
            partyA: 'BANK_ALPHA',
            partyB: 'HOSPITAL_GAMMA',
            computationType: 'AGGREGATE_OVERLAP',
            outputCohortCount: 4
        );

        $this->assertEquals(0, (int) $unsafeRun->raw_rows_exposed);
        $this->assertFalse((bool) $unsafeRun->anti_reidentification_passed);
        $this->assertFalse((bool) $unsafeRun->approved_for_export);
    }

    public function test_monetization_accounting_margin_ledger(): void
    {
        // Revenue $10,000, compute COGS $1,250 -> Net margin $8,750 (244.4)
        $entry = $this->service->recordMonetizationAccounting(
            productCode: 'DP-MARKET-BENCHMARK',
            grossRevenueUsd: 10000.0,
            computeCogsUsd: 1250.0
        );

        $this->assertEquals('DP-MARKET-BENCHMARK', $entry->product_code);
        $this->assertEquals(10000.0, (float) $entry->gross_revenue_usd);
        $this->assertEquals(1250.0, (float) $entry->compute_cogs_usd);
        $this->assertEquals(8750.0, (float) $entry->net_margin_usd);
    }

    public function test_data_monetization_audit_healthy_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->publishDataProduct('DP-OK', 'OK Index', 100.0);
        $this->service->createSharingAgreement('P-1', 'C-1', ['a']);
        $this->service->executeCleanRoomComputation('A', 'B', 'PSI', 50);
        $this->service->recordMonetizationAccounting('DP-OK', 500.0, 50.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: clean room leaked raw rows
        DB::table('data_clean_room_runs')->insert([
            'clean_room_code' => 'DCR-LEAK',
            'party_a_id' => 'LEAK_A',
            'party_b_id' => 'LEAK_B',
            'computation_type' => 'RAW_DUMP',
            'output_cohort_count' => 10,
            'raw_rows_exposed' => 50, // Inviolable breach!
            'anti_reidentification_passed' => false,
            'approved_for_export' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
