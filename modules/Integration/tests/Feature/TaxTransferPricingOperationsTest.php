<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TaxTransferPricingOperationsService;
use Tests\TestCase;

class TaxTransferPricingOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected TaxTransferPricingOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaxTransferPricingOperationsService::class);
    }

    public function test_transfer_pricing_file_and_tax_controversy_defense_flow(): void
    {
        // 438.1 Register TP documentation file (TNMM method)
        $tpFile = $this->service->registerTransferPricingFile(
            fileCode: 'TP-DOC-SG-2026',
            fiscalYear: '2026',
            relatedParty: 'ENT-SG-HUB',
            tpMethod: 'TNMM',
            armLengthMargin: 7.50,
            confidenceLabel: 'HIGH'
        );

        $this->assertEquals('TP-DOC-SG-2026', $tpFile->file_code);
        $this->assertEquals('TNMM', $tpFile->tp_method);

        // 438.3 Record tax controversy audit notice from tax authority
        $notice = $this->service->recordControversyNotice(
            noticeCode: 'NOT-DJP-AUDIT-2026',
            jurisdiction: 'DJP_INDONESIA',
            disputedTaxAmount: 4500000000.00
        );

        $this->assertEquals('NOT-DJP-AUDIT-2026', $notice->notice_code);
        $this->assertEquals('notice_received', $notice->status);

        // 438.3 & 438.5 File tax defense pack and allocate contingent provision
        $defense = $this->service->fileTaxDefenseAndProvision(
            noticeCode: 'NOT-DJP-AUDIT-2026',
            provisionAmount: 1500000000.00,
            hasDefensePack: true
        );

        $this->assertEquals('defense_filed', $defense->status);
        $this->assertTrue((bool) $defense->defense_pack_attached);

        // 438.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_inconsistent_tp_method_and_missing_defense_pack_blocked_edge_cases(): void
    {
        // 438.4 Inconsistent method across consecutive years without justification is blocked
        $this->service->registerTransferPricingFile('TP-2025', '2025', 'ENT-MY-OFFSHORE', 'TNMM', 6.0);

        try {
            $this->service->registerTransferPricingFile('TP-2026', '2026', 'ENT-MY-OFFSHORE', 'CUP', 8.0);
            $this->fail('Expected exception for inconsistent TP method');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('TP method inconsistency', $e->getMessage());
        }

        // 438.5 Missing defense pack or zero provision blocks filing
        $this->service->recordControversyNotice('NOT-IRAS-01', 'IRAS_SINGAPORE', 1000000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Defense pack must be attached and positive contingent tax provision allocated');

        $this->service->fileTaxDefenseAndProvision('NOT-IRAS-01', 0.00, false);
    }
}
