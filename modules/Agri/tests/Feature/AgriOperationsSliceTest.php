<?php

declare(strict_types=1);

namespace Modules\Agri\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Agri\Application\Actions\GradeHarvestQualityAtCollectionCenterAction;
use Modules\Agri\Application\Actions\RegisterPlasmaFarmerContractAction;
use Modules\Agri\Application\Actions\SettleFarmerPayoutNetOfInputLoanViaLedgerAction;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

class AgriOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_register_plasma_farmer_contract_with_input_loan_ledger_disbursement(): void
    {
        $action = app(RegisterPlasmaFarmerContractAction::class);

        $contract = $action->execute([
            'full_name' => 'Pak Ahmad Subarjo',
            'farmer_group_name' => 'Gapoktan Sumber Rejeki',
            'commodity' => 'cabe_merah',
            'land_area_hectares' => 2.0,
            'target_yield_kg' => 10000,
            'guaranteed_floor_price_idr_per_kg' => 30000,
            'seed_advance_value_idr' => 4000000,
            'fertilizer_advance_value_idr' => 6000000,
        ]);

        $this->assertInstanceOf(AgriContract::class, $contract);
        $this->assertEquals(10000000, $contract->total_advance_deductible_idr);
        $this->assertEquals(30000, $contract->guaranteed_floor_price_idr_per_kg);

        // Verify ledger advance receivable
        $advanceAcct = LedgerAccount::where('code', 'agri:farmer_advance_receivable:IDR')->first();
        $this->assertNotNull($advanceAcct);
        $this->assertEquals('-10000000', $advanceAcct->cached_balance);
    }

    public function test_register_plasma_farmer_contract_validates_inputs(): void
    {
        $action = app(RegisterPlasmaFarmerContractAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action->execute([
            'full_name' => 'Bad Farmer',
            'commodity' => 'cabe_merah',
            'target_yield_kg' => -50,
            'guaranteed_floor_price_idr_per_kg' => 30000,
        ]);
    }

    public function test_grade_harvest_quality_standards_and_pricing_tiers(): void
    {
        $contractAction = app(RegisterPlasmaFarmerContractAction::class);
        $gradeAction = app(GradeHarvestQualityAtCollectionCenterAction::class);

        $contract = $contractAction->execute([
            'full_name' => 'Pak Budi',
            'commodity' => 'beras_organik',
            'target_yield_kg' => 5000,
            'guaranteed_floor_price_idr_per_kg' => 20000,
        ]);

        // Grade A: 13% moisture, 1% defect -> 100% price (20,000/kg)
        $gradeA = $gradeAction->execute([
            'contract_id' => (string) $contract->id,
            'gross_weight_kg' => 1000,
            'moisture_percentage' => 13.0,
            'defect_percentage' => 1.5,
        ]);
        $this->assertEquals('GRADE_A', $gradeA['grade']);
        $this->assertEquals(20000, $gradeA['buying_price_per_kg']);
        $this->assertEquals(20000000, $gradeA['gross_payout_idr']);

        // Grade B: 15.5% moisture, 5% defect -> 90% price (18,000/kg)
        $gradeB = $gradeAction->execute([
            'contract_id' => (string) $contract->id,
            'gross_weight_kg' => 1000,
            'moisture_percentage' => 15.5,
            'defect_percentage' => 5.0,
        ]);
        $this->assertEquals('GRADE_B', $gradeB['grade']);
        $this->assertEquals(18000, $gradeB['buying_price_per_kg']);
        $this->assertEquals(18000000, $gradeB['gross_payout_idr']);

        // Grade C: 17.5% moisture -> 80% price (16,000/kg)
        $gradeC = $gradeAction->execute([
            'contract_id' => (string) $contract->id,
            'gross_weight_kg' => 1000,
            'moisture_percentage' => 17.5,
            'defect_percentage' => 8.0,
        ]);
        $this->assertEquals('GRADE_C', $gradeC['grade']);
        $this->assertEquals(16000, $gradeC['buying_price_per_kg']);
        $this->assertEquals(16000000, $gradeC['gross_payout_idr']);
    }

    public function test_settle_farmer_payout_net_of_input_loan_via_ledger(): void
    {
        $contractAction = app(RegisterPlasmaFarmerContractAction::class);
        $settleAction = app(SettleFarmerPayoutNetOfInputLoanViaLedgerAction::class);

        $contract = $contractAction->execute([
            'full_name' => 'Ibu Siti',
            'commodity' => 'bawang_merah',
            'target_yield_kg' => 4000,
            'guaranteed_floor_price_idr_per_kg' => 25000,
            'seed_advance_value_idr' => 5000000,
            'fertilizer_advance_value_idr' => 3000000, // Total advance: 8,000,000 IDR
        ]);

        // Harvest 1,000 kg Grade A at 25,000 IDR/kg = 25,000,000 IDR gross
        // Advance deduction: 8,000,000 IDR
        // Net payout: 17,000,000 IDR
        $batch = $settleAction->execute([
            'contract_id' => (string) $contract->id,
            'collection_center_id' => 'CC-BREBES-01',
            'gross_weight_kg' => 1000,
            'grade' => 'GRADE_A',
            'destination_unit' => 'RESTO_CK01',
        ]);

        $this->assertInstanceOf(AgriCollectionBatch::class, $batch);
        $this->assertEquals(25000000, $batch->gross_payout_idr);
        $this->assertEquals(8000000, $batch->advance_deduction_idr);
        $this->assertEquals(17000000, $batch->net_payout_idr);

        // Contract deductible should now be 0
        $contract->refresh();
        $this->assertEquals(0, $contract->total_advance_deductible_idr);

        // Verify ledger postings:
        // Inventory raw commodities: debit 25,000,000 -> -25000000
        $invAcct = LedgerAccount::where('code', 'inventory:agri:raw_commodities:IDR')->first();
        $this->assertEquals('-25000000', $invAcct->cached_balance);

        // Farmer payable: credit 17,000,000 -> 17000000
        $payableAcct = LedgerAccount::where('code', 'ap:agri:farmer_payable:IDR')->first();
        $this->assertEquals('17000000', $payableAcct->cached_balance);

        // Advance receivable was debited 8M at contract creation, credited 8M at settlement -> balance 0
        $advanceAcct = LedgerAccount::where('code', 'agri:farmer_advance_receivable:IDR')->first();
        $this->assertEquals('0', $advanceAcct->cached_balance);
    }

    public function test_settle_farmer_payout_when_loan_exceeds_gross_yield(): void
    {
        $contractAction = app(RegisterPlasmaFarmerContractAction::class);
        $settleAction = app(SettleFarmerPayoutNetOfInputLoanViaLedgerAction::class);

        $contract = $contractAction->execute([
            'full_name' => 'Pak Joko',
            'commodity' => 'cabe_merah',
            'target_yield_kg' => 2000,
            'guaranteed_floor_price_idr_per_kg' => 20000,
            'seed_advance_value_idr' => 10000000, // Total advance: 10,000,000 IDR
        ]);

        // Harvest only 200 kg Grade A at 20,000 = 4,000,000 IDR gross
        // Max deduction: 4,000,000 IDR
        // Net payout: 0 IDR
        // Remaining contract advance: 6,000,000 IDR
        $batch = $settleAction->execute([
            'contract_id' => (string) $contract->id,
            'collection_center_id' => 'CC-SUBANG-02',
            'gross_weight_kg' => 200,
            'grade' => 'GRADE_A',
        ]);

        $this->assertEquals(4000000, $batch->gross_payout_idr);
        $this->assertEquals(4000000, $batch->advance_deduction_idr);
        $this->assertEquals(0, $batch->net_payout_idr);

        $contract->refresh();
        $this->assertEquals(6000000, $contract->total_advance_deductible_idr);
    }

    public function test_agri_operations_controller_views_and_actions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Dashboard
        $resDash = $this->actingAs($user)->get(route('agri.dashboard'));
        $resDash->assertStatus(200);
        $resDash->assertSee('Agricultural Supply Chain');

        // Contracts Index & Register
        $resContracts = $this->actingAs($user)->get(route('agri.contracts.index'));
        $resContracts->assertStatus(200);

        $resRegister = $this->actingAs($user)->post(route('agri.contracts.register'), [
            'full_name' => 'Pak Wawan',
            'farmer_group_name' => 'Tani Jaya',
            'commodity' => 'cabe_merah',
            'land_area_hectares' => 1.5,
            'target_yield_kg' => 6000,
            'guaranteed_floor_price_idr_per_kg' => 28000,
            'seed_advance_value_idr' => 2000000,
        ]);
        $resRegister->assertRedirect(route('agri.contracts.index'));
        $this->assertDatabaseHas('agri_farmers', ['full_name' => 'Pak Wawan']);

        // Grading Index
        $resGrading = $this->actingAs($user)->get(route('agri.grading.index'));
        $resGrading->assertStatus(200);

        // Payout Index
        $resPayout = $this->actingAs($user)->get(route('agri.payout.index'));
        $resPayout->assertStatus(200);
    }
}
