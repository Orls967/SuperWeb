<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AquacultureExportService;
use Tests\TestCase;

/**
 * Fase 171 — Aquaculture Export, Seafood Traceability & Blue ESG Tests
 *
 * Covers:
 *  (a) lineage completeness and hash validation
 *  (b) export quantity <= verified harvest
 *  (c) Blue ESG claims strictly tied to satellite evidence
 *  (d) marine:audit = 0 discrepancy
 */
class AquacultureExportTest extends TestCase
{
    use RefreshDatabase;

    protected AquacultureExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AquacultureExportService::class);
    }

    /**
     * (a) Lot passport issued with cryptographic lineage hash.
     */
    public function test_lot_passport_lineage_hash(): void
    {
        $passport = $this->service->issuePassport('HATCHERY-BALI-01', 'FARM-LOMBOK-02', 8000.0);

        $this->assertEquals(8000.00, (float) $passport->verified_harvest_weight_kg);
        $this->assertNotNull($passport->lineage_hash);
    }

    /**
     * (b) Export quantity bounded by verified harvest.
     */
    public function test_export_quantity_bounded_by_harvest(): void
    {
        $passport = $this->service->issuePassport('HATCHERY-BALI-02', 'FARM-SUMBAWA-01', 5000.0);

        // 1. Valid export of 3,000 kg (<= 5,000 kg)
        $cert = $this->service->issueExportCertificate($passport->passport_code, 3000.0, 'JP');
        $this->assertEquals(3000.00, (float) $cert->export_quantity_kg);
        $this->assertSame('JP', $cert->destination_country_iso);

        // 2. Excess export of 6,000 kg (> 5,000 kg) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->issueExportCertificate($passport->passport_code, 6000.0, 'US');
    }

    /**
     * (c) Blue ESG carbon credit requires satellite evidence.
     */
    public function test_blue_esg_credit_issuance_requires_evidence(): void
    {
        // Missing satellite evidence -> Rejected
        try {
            $this->service->issueBlueCarbonCredit('POLY-MANGROVE-01', 9.2, 500.0, false);
            $this->fail('Expected exception for missing evidence.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Satellite evidence required', $e->getMessage());
        }

        // With evidence -> Issued
        $credit = $this->service->issueBlueCarbonCredit('POLY-MANGROVE-01', 9.2, 500.0, true);
        $this->assertTrue((bool) $credit->issued);
        $this->assertEquals(500.00, (float) $credit->blue_carbon_tons);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_aquaculture_export_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
