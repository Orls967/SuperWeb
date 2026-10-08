<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\CorporateTaxEngineService;
use Tests\TestCase;

class CorporateTaxEngineTest extends TestCase
{
    use RefreshDatabase;

    protected CorporateTaxEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CorporateTaxEngineService::class);
    }

    public function test_pillar_two_global_minimum_tax_calculation(): void
    {
        // Low-tax jurisdiction (e.g. 9% in Dubai/Cayman vs 15% Pillar Two minimum threshold) (274.1 & 274.4)
        // Income = $10,000,000; Domestic tax = 9% ($900,000); Top-up tax = 6% ($600,000); Total = $1,500,000
        $provision = $this->service->computeTaxProvision(
            provisionCode: 'PROV-2026-DXB-01',
            countryCode: 'AE',
            taxPeriod: '2026-FY',
            pretaxIncomeUsd: 10000000.0,
            domesticTaxRatePct: 9.0
        );

        $this->assertEquals(600000.0, (float) $provision->pillar_two_top_up_tax_usd);
        $this->assertEquals(1500000.0, (float) $provision->total_tax_provision_usd);
        $this->assertTrue((bool) $provision->accounting_close_blocked); // Accounting close blocked initially
    }

    public function test_tax_director_approval_unblocks_accounting_close(): void
    {
        // 1. Initial computation has close blocked (274.6)
        $this->service->computeTaxProvision('PROV-2026-IDN', 'ID', '2026-FY', 5000000.0, 22.0);

        // 2. Tax Director approves -> unblocks accounting close (274.1 & 274.6)
        $approved = $this->service->approveTaxProvision('PROV-2026-IDN', 'TAX_DIR_SITUMORANG');
        $this->assertTrue((bool) $approved->is_tax_director_approved);
        $this->assertFalse((bool) $approved->accounting_close_blocked);
        $this->assertEquals('TAX_DIR_SITUMORANG', $approved->approved_by_director_id);
    }

    public function test_tax_lineage_voucher_and_retroactive_recompute(): void
    {
        // 1. Record voucher with evidence pack (274.2)
        $voucher = $this->service->recordTaxLineageVoucher(
            voucherCode: 'VCH-ROYALTY-2026-01',
            provisionCode: 'PROV-2026-IDN',
            sourceGlJournalRef: 'GL-JV-998877',
            taxableAmountUsd: 250000.0,
            evidencePackDoc: 'DOC-WHT-ROYALTY-EVIDENCE.PDF',
            ruleVersion: 1
        );
        $this->assertEquals(1, (int) $voucher->rule_version);
        $this->assertFalse((bool) $voucher->is_retroactive_recomputed);

        // 2. Retroactive statutory rate change recomputed (274.5 Edge Case)
        $recomputed = $this->service->recomputeRetroactiveRuleChange('VCH-ROYALTY-2026-01', 2, 220000.0);
        $this->assertEquals(2, (int) $recomputed->rule_version);
        $this->assertEquals(220000.0, (float) $recomputed->taxable_amount_usd);
        $this->assertTrue((bool) $recomputed->is_retroactive_recomputed);
    }

    public function test_tax_controversy_defense_pack(): void
    {
        // Tax controversy readiness with audit evidence (274.3 & 274.7)
        $case = $this->service->registerTaxControversy(
            caseCode: 'CASE-TP-CROSSBORDER-01',
            taxAuthority: 'DIREKTORAT_JENDERAL_PAJAK',
            category: 'TRANSFER_PRICING',
            contestedAmountUsd: 3500000.0,
            defensePackDoc: 'TP-LOCAL-FILE-BENCHMARKING-STUDY-2026.PDF'
        );

        $this->assertEquals('DEFENSE_PREPARED', $case->outcome_status);
        $this->assertNotNull($case->defense_pack_doc);
    }

    public function test_corporate_tax_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->computeTaxProvision('PROV-AUD', 'SG', '2026', 1000.0, 17.0);
        $this->service->approveTaxProvision('PROV-AUD', 'DIR_1');
        $this->service->recordTaxLineageVoucher('VCH-AUD', 'PROV-AUD', 'GL-1', 100.0, 'DOC.PDF');
        $this->service->registerTaxControversy('CASE-AUD', 'IRS', 'WHT', 100.0, 'DEF.PDF');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: low tax provision (< 15%) without Pillar Two top-up tax
        DB::table('corporate_tax_provisions')->insert([
            'provision_code' => 'PROV-BREACH-PILLAR2',
            'jurisdiction_country_code' => 'KY',
            'tax_year_period' => '2026',
            'pretax_accounting_income_usd' => 1000000.0,
            'effective_tax_rate_pct' => 5.0, // < 15%!
            'pillar_two_top_up_tax_usd' => 0.0, // Discrepancy: missing top up!
            'total_tax_provision_usd' => 50000.0,
            'is_tax_director_approved' => false,
            'accounting_close_blocked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
