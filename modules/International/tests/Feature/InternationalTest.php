<?php

declare(strict_types=1);

namespace Modules\International\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\International\Application\Services\InternationalService;
use Tests\TestCase;

class InternationalTest extends TestCase
{
    use RefreshDatabase;

    protected InternationalService $intlService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intlService = app(InternationalService::class);
    }

    public function test_51_1_register_foreign_entity(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_JP_01',
            legalName: 'Kyoto Advanced Robotics KK',
            jurisdictionCountry: 'JP',
            regNumber: 'JP-992019-A',
            functionalCurrency: 'JPY',
            arbitration: 'SIAC',
            hasApostille: true,
            amlScreened: true,
            taxResidence: 'JP'
        );

        $this->assertDatabaseHas('intl_foreign_entities', [
            'entity_code' => 'FOR_JP_01',
            'legal_name' => 'Kyoto Advanced Robotics KK',
            'functional_currency' => 'JPY',
            'has_apostille' => 1,
            'aml_screened' => 1,
        ]);
    }

    public function test_51_2_joint_venture_structure_and_capital_calls(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_DE_01',
            legalName: 'Stuttgart Engineering AG',
            jurisdictionCountry: 'DE',
            regNumber: 'DE-88219-B',
            functionalCurrency: 'EUR'
        );

        $jv = $this->intlService->establishJointVenture(
            entity: $entity,
            name: 'PT Indo Stuttgart Jaya',
            jvType: 'equity',
            localSharePercent: 51.0,
            foreignSharePercent: 49.0,
            committedCapitalIdr: 10_000_000_000,
            minorityVetoRights: true
        );

        $this->assertDatabaseHas('intl_joint_ventures', [
            'id' => $jv->id,
            'local_share_percent' => 51.0,
            'foreign_share_percent' => 49.0,
            'total_committed_capital_idr' => 10_000_000_000,
            'paid_in_capital_idr' => 0,
            'minority_veto_rights' => 1,
        ]);

        // Record first capital call: 4,000,000,000 IDR
        $jv = $this->intlService->recordCapitalCall($jv, 4_000_000_000);
        $this->assertEquals(4_000_000_000, $jv->paid_in_capital_idr);

        // Disallow capital call exceeding committed capital
        $this->expectException(InvalidArgumentException::class);
        $this->intlService->recordCapitalCall($jv, 7_000_000_000); // 4 + 7 = 11 > 10
    }

    public function test_51_3_technology_license_and_royalty_calculation(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_US_01',
            legalName: 'Silicon Micro Devices Inc',
            jurisdictionCountry: 'US',
            regNumber: 'US-DEL-9912',
            functionalCurrency: 'USD'
        );

        $license = $this->intlService->registerTechnologyLicense(
            entity: $entity,
            title: 'Automotive Sensor Patent License',
            licenseType: 'patent',
            royaltyRatePercent: 4.5,
            minimumAnnualGuaranteeIdr: 120_000_000, // 10jt / bulan
            startDate: '2026-01-01',
            expiryDate: '2028-12-31'
        );

        // Case A: Sales 1,000,000,000 IDR -> 4.5% = 45,000,000 IDR (> MAG 10jt)
        $resA = $this->intlService->calculateRoyalty($license, 1_000_000_000);
        $this->assertEquals(45_000_000, $resA['calculated_royalty_idr']);
        $this->assertEquals(45_000_000, $resA['payable_royalty_idr']);

        // Case B: Sales 100,000,000 IDR -> 4.5% = 4,500,000 IDR (< MAG 10jt) -> fallback MAG
        $resB = $this->intlService->calculateRoyalty($license, 100_000_000);
        $this->assertEquals(4_500_000, $resB['calculated_royalty_idr']);
        $this->assertEquals(10_000_000, $resB['payable_royalty_idr']);
    }

    public function test_51_4_oem_contract_manufacturing(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_KR_01',
            legalName: 'Seoul Battery Tech Corp',
            jurisdictionCountry: 'KR',
            regNumber: 'KR-SEOUL-88',
            functionalCurrency: 'KRW'
        );

        $oem = $this->intlService->registerOemContract(
            entity: $entity,
            type: 'OEM',
            designName: 'High Density EV Battery Pack v2',
            tollingFeePerUnitIdr: 250_000,
            qaStandard: 'IATF16949'
        );

        $this->assertDatabaseHas('intl_oem_contracts', [
            'id' => $oem->id,
            'type' => 'OEM',
            'tolling_fee_per_unit_idr' => 250_000,
            'qa_standard' => 'IATF16949',
        ]);
    }

    public function test_51_5_tech_transfer_milestone_delivery(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_SG_01',
            legalName: 'Temasek R&D Labs Pte Ltd',
            jurisdictionCountry: 'SG',
            regNumber: 'SG-UEN-9988',
            functionalCurrency: 'SGD'
        );

        $tt = $this->intlService->createTechTransfer(
            entity: $entity,
            title: 'Solid State Battery Technology Transfer',
            totalValueIdr: 5_000_000_000
        );

        $this->assertEquals('in_progress', $tt->status);

        // Milestone 1: Blueprint Handover (2,000,000,000 IDR)
        $tt = $this->intlService->acceptTransferMilestone($tt, 'BLUEPRINT_HANDOVER', 2_000_000_000);
        $this->assertEquals(2_000_000_000, $tt->accepted_value_idr);
        $this->assertEquals('in_progress', $tt->status);

        // Milestone 2: Pilot Line Commissioning & Final Sign-off (3,000,000,000 IDR)
        $tt = $this->intlService->acceptTransferMilestone($tt, 'COMMISSIONING_SIGNOFF', 3_000_000_000);
        $this->assertEquals(5_000_000_000, $tt->accepted_value_idr);
        $this->assertEquals('completed', $tt->status);
        $this->assertTrue($tt->signoff_completed);
    }

    public function test_51_7_tax_treaty_p3b_withholding_tax(): void
    {
        $this->intlService->registerTaxTreaty(
            countryCode: 'SG',
            countryName: 'Singapore',
            royaltyRate: 10.0,
            interestRate: 10.0,
            dividendRate: 10.0,
            servicesRate: 10.0
        );

        // Scenario 1: Singapore with valid DGT -> 10% WHT
        $res1 = $this->intlService->calculateWithholdingTax(
            grossAmountIdr: 100_000_000,
            incomeType: 'royalty',
            countryCode: 'SG',
            hasValidDgt: true
        );

        $this->assertEquals(10.0, $res1['effective_rate_percent']);
        $this->assertEquals(10_000_000, $res1['wht_tax_idr']);
        $this->assertEquals(90_000_000, $res1['net_payout_idr']);

        // Scenario 2: Singapore WITHOUT valid DGT -> falls back to domestic 20%
        $res2 = $this->intlService->calculateWithholdingTax(
            grossAmountIdr: 100_000_000,
            incomeType: 'royalty',
            countryCode: 'SG',
            hasValidDgt: false
        );

        $this->assertEquals(20.0, $res2['effective_rate_percent']);
        $this->assertEquals(20_000_000, $res2['wht_tax_idr']);
        $this->assertEquals(80_000_000, $res2['net_payout_idr']);
    }

    public function test_51_9_international_audit_and_command(): void
    {
        $entity = $this->intlService->registerForeignEntity(
            code: 'FOR_TW_01',
            legalName: 'Hsinchu Microelectronics Corp',
            jurisdictionCountry: 'TW',
            regNumber: 'TW-887711',
            functionalCurrency: 'TWD'
        );

        $this->intlService->establishJointVenture(
            entity: $entity,
            name: 'PT Indo Taiwan Semi',
            jvType: 'contractual',
            localSharePercent: 60.0,
            foreignSharePercent: 40.0,
            committedCapitalIdr: 2_000_000_000
        );

        $audit = $this->intlService->auditInternational();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['jv_count']);

        $this->artisan('intl:audit')
            ->expectsOutputToContain('International cooperation audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_51_10_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('international.index'))
            ->assertOk()
            ->assertSee('Kerja Sama Internasional');

        $this->actingAs($user)
            ->get(route('international.jvs'))
            ->assertOk()
            ->assertSee('Joint Ventures');
    }
}
