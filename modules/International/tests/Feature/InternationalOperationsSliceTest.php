<?php

declare(strict_types=1);

namespace Modules\International\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\International\Application\Actions\ApplyTaxTreatyDtaReliefAction;
use Modules\International\Application\Actions\CalculateCrossBorderRoyaltyWithWhtAction;
use Modules\International\Application\Actions\ReconcileOemToolingAmortizationAction;
use Modules\International\Domain\Models\ForeignEntity;
use Modules\International\Domain\Models\OemContract;
use Modules\International\Domain\Models\TaxTreaty;
use Modules\International\Domain\Models\TechnologyLicense;
use Tests\TestCase;

class InternationalOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ForeignEntity $foreignEntity;

    protected TechnologyLicense $license;

    protected OemContract $oemContract;

    protected TaxTreaty $treaty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        $this->foreignEntity = ForeignEntity::create([
            'entity_code' => 'FOR_SG_01',
            'legal_name' => 'Singapore HighTech Pte Ltd',
            'jurisdiction_country' => 'SG',
            'registration_number' => 'UEN-202619082',
            'functional_currency' => 'USD',
            'arbitration_jurisdiction' => 'SIAC',
            'has_apostille' => true,
            'aml_screened' => true,
            'tax_residence_country' => 'SG',
            'status' => 'active',
        ]);

        $this->treaty = TaxTreaty::create([
            'country_code' => 'SG',
            'country_name' => 'Singapore',
            'standard_wht_rate' => 20.0,
            'treaty_royalty_rate' => 10.0,
            'treaty_interest_rate' => 10.0,
            'treaty_dividend_rate' => 10.0,
            'treaty_services_rate' => 5.0,
            'dgt_form_required' => true,
        ]);

        $this->license = TechnologyLicense::create([
            'license_code' => 'LIC-SG-2026-001',
            'title' => 'Advanced AI Battery Optimization IP',
            'foreign_entity_id' => $this->foreignEntity->id,
            'license_type' => 'patent',
            'territory' => 'INDONESIA',
            'is_exclusive' => true,
            'royalty_rate_percent' => 5.0,
            'minimum_annual_guarantee_idr' => 10000000,
            'start_date' => '2026-01-01',
            'expiry_date' => '2028-12-31',
            'status' => 'active',
        ]);

        $this->oemContract = OemContract::create([
            'contract_number' => 'OEM-2026-009',
            'foreign_entity_id' => $this->foreignEntity->id,
            'type' => 'OEM',
            'product_design_name' => 'High-Precision Power Module',
            'tolling_fee_per_unit_idr' => 25000,
            'consignment_materials_tracked' => true,
            'nda_signed' => true,
            'qa_standard' => 'ISO9001',
            'status' => 'active',
        ]);
    }

    public function test_calculate_cross_border_royalty_with_treaty_wht(): void
    {
        $action = app(CalculateCrossBorderRoyaltyWithWhtAction::class);

        // Sales revenue: 1,000,000,000 IDR
        // Royalty @ 5% = 50,000,000 IDR
        // With valid DGT: 10% treaty WHT = 5,000,000 IDR
        // Net remittance = 45,000,000 IDR
        $res = $action->execute($this->license, 1000000000, true);

        $this->assertSame(50000000, $res['gross_royalty_idr']);
        $this->assertSame(10.0, $res['wht_rate_percent']);
        $this->assertSame(5000000, $res['withholding_tax_idr']);
        $this->assertSame(45000000, $res['net_remittance_idr']);
        $this->assertTrue($res['treaty_applied']);
    }

    public function test_calculate_cross_border_royalty_without_dgt_falls_back_to_domestic_rate(): void
    {
        $action = app(CalculateCrossBorderRoyaltyWithWhtAction::class);

        // Without DGT form: 20% standard PPh 26
        // Gross royalty = 50,000,000 IDR -> WHT = 10,000,000 IDR -> Net = 40,000,000 IDR
        $res = $action->execute($this->license, 1000000000, false);

        $this->assertSame(20.0, $res['wht_rate_percent']);
        $this->assertSame(10000000, $res['withholding_tax_idr']);
        $this->assertSame(40000000, $res['net_remittance_idr']);
        $this->assertFalse($res['treaty_applied']);
    }

    public function test_apply_tax_treaty_dta_relief_savings_computation(): void
    {
        $action = app(ApplyTaxTreatyDtaReliefAction::class);

        // Gross dividend: 200,000,000 IDR
        // Standard PPh 26 (20%): 40,000,000 IDR
        // Treaty SG Dividend (10%): 20,000,000 IDR
        // Tax savings: 20,000,000 IDR
        $res = $action->execute('SG', 'dividend', 200000000, true);

        $this->assertSame(40000000, $res['standard_tax_idr']);
        $this->assertSame(20000000, $res['actual_tax_idr']);
        $this->assertSame(20000000, $res['tax_savings_idr']);
        $this->assertTrue($res['relief_granted']);
    }

    public function test_apply_tax_treaty_dta_relief_invalid_income_type_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(ApplyTaxTreatyDtaReliefAction::class);

        $action->execute('SG', 'unknown_type', 100000000, true);
    }

    public function test_reconcile_oem_tooling_amortization_calculations(): void
    {
        $action = app(ReconcileOemToolingAmortizationAction::class);

        // Contract tolling fee per unit: 25,000 IDR
        // Tooling total cost: 50,000,000 IDR
        // Total expected units: 10,000 units -> 5,000 IDR/unit amortization
        // Units produced: 2,000 units
        // Total tolling fee: 2,000 * 25,000 = 50,000,000 IDR
        // Amortized tooling: 2,000 * 5,000 = 10,000,000 IDR
        // Total payable: 60,000,000 IDR
        // Remaining tooling book value: 40,000,000 IDR
        $res = $action->execute($this->oemContract, 2000, 50000000, 10000);

        $this->assertSame(25000, $res['tolling_fee_unit_idr']);
        $this->assertSame(50000000, $res['total_tolling_fee_idr']);
        $this->assertSame(5000, $res['tooling_amortization_per_unit_idr']);
        $this->assertSame(10000000, $res['total_tooling_amortized_idr']);
        $this->assertSame(60000000, $res['total_payable_idr']);
        $this->assertSame(40000000, $res['remaining_unamortized_tooling_idr']);
    }

    public function test_international_tax_and_royalty_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Dashboard
        $respDash = $this->get('/international/tax-royalty');
        $respDash->assertOk();

        // 2. Royalty calculation endpoint
        $respRoyalty = $this->postJson("/international/tax-royalty/royalty/{$this->license->id}", [
            'sales_revenue_idr' => 500000000,
            'has_valid_dgt_form' => true,
        ]);
        $respRoyalty->assertOk();
        $this->assertSame(25000000, $respRoyalty->json('data.gross_royalty_idr'));
        $this->assertSame(22500000, $respRoyalty->json('data.net_remittance_idr'));

        // 3. Treaty relief endpoint
        $respRelief = $this->postJson('/international/tax-royalty/treaty-relief', [
            'country_code' => 'SG',
            'income_type' => 'services',
            'gross_payment_idr' => 100000000,
            'has_valid_dgt1_form' => true,
        ]);
        $respRelief->assertOk();
        $this->assertSame(5000000, $respRelief->json('data.actual_tax_idr'));

        // 4. OEM tooling endpoint
        $respOem = $this->postJson("/international/tax-royalty/oem-tooling/{$this->oemContract->id}", [
            'units_produced' => 1000,
            'tooling_total_cost_idr' => 20000000,
            'total_contract_expected_units' => 5000,
        ]);
        $respOem->assertOk();
        $this->assertSame(25000000, $respOem->json('data.total_tolling_fee_idr'));
    }
}
