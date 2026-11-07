<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningSmelter;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\Application\Actions\AbortSmelterFurnaceRunAction;
use Modules\Mining\Application\Actions\AuditSmelterChainOfCustodyAction;
use Modules\Mining\Application\Actions\CalculateSmelterSlagBasicityAction;
use Modules\Mining\Application\Actions\ConductSmelterQualityAssayAction;
use Modules\Mining\Application\Actions\CreateMetalsTradingContractAction;
use Modules\Mining\Application\Actions\CreateSmelterFurnaceRunAction;
use Modules\Mining\Application\Actions\EvaluateLmeContangoBackwardationAction;
use Modules\Mining\Application\Actions\EvaluateSmelterEnergyEmissionIntensityAction;
use Modules\Mining\Application\Actions\ExecuteLmeMarkToMarketValuationAction;
use Modules\Mining\Application\Actions\ExecuteSmelterEnergyBalanceAction;
use Modules\Mining\Application\Actions\ExportSmelterTradingReportCsvAction;
use Modules\Mining\Application\Actions\IssueMetalsWarehouseReceiptAction;
use Modules\Mining\Application\Actions\LockMetalsTradingContractPriceAction;
use Modules\Mining\Application\Actions\PledgeWarehouseReceiptBankScfAction;
use Modules\Mining\Application\Actions\ReconcileSmelterMassBalanceAction;
use Modules\Mining\Application\Actions\RecordFurnaceTappingOutputAction;
use Modules\Mining\Application\Actions\RecycleSmelterByproductSlagAction;
use Modules\Mining\Application\Actions\RegisterWarehouseReceiptBappebtiAction;
use Modules\Mining\Application\Actions\ReleaseWarehouseReceiptCollateralAction;
use Modules\Mining\Application\Actions\RunMetalsHilirisasiPipelineAction;
use Modules\Mining\Application\Actions\SettleMetalsTradingContractAction;
use Modules\Mining\Application\Actions\TriggerMetalsTradingMarginCallAction;
use Modules\Mining\Application\Actions\ValidateWarehouseReceiptIntegrityAction;
use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningSmelterFurnace;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Tests\TestCase;

class MiningSmelterAndTradingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Skenario 1: Siklus Run Tanur Smelter RKEF & Solver Termodinamika M10.
     */
    public function test_smelter_furnace_batch_run_and_pyrometallurgical_energy_balance_cycle(): void
    {
        $furnace = MiningSmelterFurnace::create([
            'furnace_code' => 'TEST-FURN-01',
            'name' => 'Test Electric Arc Furnace 45 MVA',
            'furnace_type' => 'RKEF_ELECTRIC_ARC',
            'rated_capacity_tons_day' => 500.0,
            'transformer_power_mva' => 45.0,
            'operating_temperature_celsius' => 1550.0,
            'status' => 'OPERATIONAL',
        ]);

        $runAction = app(CreateSmelterFurnaceRunAction::class);
        $balanceAction = app(ExecuteSmelterEnergyBalanceAction::class);
        $tapAction = app(RecordFurnaceTappingOutputAction::class);
        $emissionAction = app(EvaluateSmelterEnergyEmissionIntensityAction::class);
        $basicityAction = app(CalculateSmelterSlagBasicityAction::class);

        $run = $runAction->execute((string) $furnace->id, [
            'run_code' => 'RUN-TEST-001',
            'ore_feed_wmt' => 150.0,
            'ore_moisture_pct' => 32.0,
            'ore_ni_grade_pct' => 1.85,
            'ore_fe_grade_pct' => 18.00,
            'coal_reductant_tons' => 16.0,
            'silica_flux_tons' => 9.0,
            'power_consumption_mwh' => 48.0,
            'tapping_temperature_c' => 1520.0,
        ]);

        $this->assertEquals('RUN-TEST-001', $run->run_code);
        $this->assertEquals(FurnaceRunStatus::DRAFT->value, $run->status);

        // Hitung neraca energi
        $balanceResult = $balanceAction->execute($run);
        $this->assertTrue($balanceResult->isThermallyFeasible);
        $this->assertGreaterThan(0.0, $run->metal_output_tons);

        // Catat tapping
        $tapped = $tapAction->execute($run, 18.5, 62.0, 1530.0);
        $this->assertEquals(FurnaceRunStatus::COMPLETED->value, $tapped->status);

        // Evaluasi emisi & basicity
        $emission = $emissionAction->execute($run);
        $this->assertGreaterThan(0.0, $emission['total_tco2e']);

        $basicity = $basicityAction->execute(42.0, 22.0, 2.5);
        $this->assertGreaterThan(0.0, $basicity['basicity_ratio']);
    }

    /**
     * Skenario 2: Sertifikasi Uji Mutu Laboratorium Spektrometri & Validasi LME.
     */
    public function test_quality_assay_certification_and_lme_specification_compliance(): void
    {
        $furnace = MiningSmelterFurnace::create([
            'furnace_code' => 'TEST-FURN-02',
            'name' => 'Furnace Test 02',
        ]);

        $run = MiningSmelterFurnaceRun::create([
            'furnace_id' => $furnace->id,
            'run_code' => 'RUN-ASY-TEST-01',
            'ore_feed_wmt' => 100.0,
            'status' => 'COMPLETED',
        ]);

        $assayAction = app(ConductSmelterQualityAssayAction::class);
        $assay = $assayAction->execute($run, [
            'assay_number' => 'ASY-TEST-001',
            'primary_metal_grade_pct' => 13.50,
            'iron_fe_pct' => 80.20,
            'carbon_c_pct' => 2.05,
            'silicon_si_pct' => 1.75,
            'phosphorus_p_pct' => 0.021,
            'sulfur_s_pct' => 0.029,
        ]);

        $this->assertEquals('ASY-TEST-001', $assay->assay_number);
        $this->assertTrue($assay->meets_lme_specification);
        $this->assertNotEmpty($assay->hmac_signature);
    }

    /**
     * Skenario 3: Kontrak Trading LME, Lock Price & Valuasi Mark-to-Market M11.
     */
    public function test_metals_trading_lme_hedging_and_mark_to_market_margin_call(): void
    {
        $contractAction = app(CreateMetalsTradingContractAction::class);
        $lockAction = app(LockMetalsTradingContractPriceAction::class);
        $mtmAction = app(ExecuteLmeMarkToMarketValuationAction::class);
        $marginCallAction = app(TriggerMetalsTradingMarginCallAction::class);
        $settleAction = app(SettleMetalsTradingContractAction::class);
        $contangoAction = app(EvaluateLmeContangoBackwardationAction::class);

        $contract = $contractAction->execute([
            'contract_number' => 'CTR-TEST-001',
            'counterparty_name' => 'Trafigura Metals Desk',
            'metal_symbol' => 'LME-NI',
            'contract_type' => 'PHYSICAL_OFFTAKE',
            'contract_quantity_metric_tons' => 500.0,
            'fixed_price_usd_cents_per_ton' => 1650000,
        ]);

        $this->assertEquals('CTR-TEST-001', $contract->contract_number);
        $this->assertEquals(TradingContractStatus::DRAFT->value, $contract->status);

        // Kunci harga
        $locked = $lockAction->execute($contract, 1660000);
        $this->assertEquals(TradingContractStatus::PRICE_LOCKED->value, $locked->status);

        // Valuasi MTM
        $mtmResult = $mtmAction->execute($contract, 'SHORT', 1620000);
        $this->assertGreaterThan(0, $mtmResult->unrealizedPnlUsdCents);

        // Cek margin call
        $marginAlert = $marginCallAction->execute($contract);
        $this->assertIsArray($marginAlert);

        // Evaluasi struktur contango
        $contango = $contangoAction->execute('LME-NI');
        $this->assertNotEmpty($contango['market_structure']);

        // Settle kontrak
        $settled = $settleAction->execute($contract, true);
        $this->assertEquals(TradingContractStatus::FINANCIALLY_SETTLED->value, $settled->status);
    }

    /**
     * Skenario 4: Resi Gudang Digital SRG ISWARE, Haircut M12 & Penjaminan Bank SCF.
     */
    public function test_warehouse_receipt_srg_bappebti_registration_and_bank_scf_lifecycle(): void
    {
        $furnace = MiningSmelterFurnace::create(['furnace_code' => 'FURN-TEST-04', 'name' => 'Furnace 04']);
        $run = MiningSmelterFurnaceRun::create(['furnace_id' => $furnace->id, 'run_code' => 'RUN-SRG-01', 'status' => 'COMPLETED']);
        $assay = app(ConductSmelterQualityAssayAction::class)->execute($run, ['assay_number' => 'ASY-SRG-01']);

        $receiptAction = app(IssueMetalsWarehouseReceiptAction::class);
        $bappebtiAction = app(RegisterWarehouseReceiptBappebtiAction::class);
        $pledgeAction = app(PledgeWarehouseReceiptBankScfAction::class);
        $releaseAction = app(ReleaseWarehouseReceiptCollateralAction::class);
        $verifyIntegrityAction = app(ValidateWarehouseReceiptIntegrityAction::class);

        $receipt = $receiptAction->execute($assay, 200.0, [
            'receipt_number' => 'SRG-TEST-001',
            'unit_market_price_idr_per_ton' => 240000000,
        ]);

        $this->assertEquals('SRG-TEST-001', $receipt->receipt_number);
        $this->assertEquals(WarehouseReceiptStatus::ISSUED->value, $receipt->status);

        // Registrasi Bappebti
        $registered = $bappebtiAction->execute($receipt);
        $this->assertEquals(WarehouseReceiptStatus::REGISTERED_BAPPEBTI->value, $registered->status);

        // Penjaminan SCF Bank
        $pledged = $pledgeAction->execute($registered, 'PT Bank Mandiri (Persero) Tbk');
        $this->assertEquals(WarehouseReceiptStatus::PLEDGED_SCF->value, $pledged->status);

        // Validasi integritas segel
        $integrity = $verifyIntegrityAction->execute($pledged);
        $this->assertTrue($integrity['is_valid']);

        // Pelepasan agunan
        $released = $releaseAction->execute($pledged, true);
        $this->assertEquals(WarehouseReceiptStatus::FULLY_RELEASED->value, $released->status);
    }

    /**
     * Skenario 5: Pipeline Hilirisasi Lengkap (Saga), Produk Sampingan Slag, Pajak Karbon & Audit Trail.
     */
    public function test_end_to_end_metals_hilirisasi_pipeline_saga_and_byproduct_slag_monetization(): void
    {
        $furnace = MiningSmelterFurnace::create([
            'furnace_code' => 'FURN-TEST-05',
            'name' => 'Furnace 05',
        ]);

        $run = app(CreateSmelterFurnaceRunAction::class)->execute((string) $furnace->id, [
            'run_code' => 'RUN-HILIRISASI-01',
            'ore_feed_wmt' => 140.0,
            'ore_moisture_pct' => 30.0,
            'ore_ni_grade_pct' => 1.88,
            'ore_fe_grade_pct' => 18.20,
            'coal_reductant_tons' => 15.0,
            'silica_flux_tons' => 8.5,
            'power_consumption_mwh' => 45.0,
        ]);

        // Jalankan Saga Hilirisasi
        $pipelineAction = app(RunMetalsHilirisasiPipelineAction::class);
        $result = $pipelineAction->execute($run);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['receipt_number']);
        $this->assertContains('FURNACE_RUN_BATCH_COMPLETED', $result['audit_trail']);
        $this->assertContains('WAREHOUSE_RECEIPT_ISSUED', $result['audit_trail']);
        $this->assertContains('BYPRODUCT_SLAG_MONETIZED_AND_MANIFESTED', $result['audit_trail']);

        // Daur ulang produk sampingan terak slag
        $byproduct = $run->byproducts()->first();
        if ($byproduct) {
            $recycleAction = app(RecycleSmelterByproductSlagAction::class);
            $recycled = $recycleAction->execute($byproduct, 15.0);
            $this->assertEquals('RECYCLED', $recycled->status);
        }

        // Rekonsiliasi neraca massa & audit rantai kustodi
        $reconcileAction = app(ReconcileSmelterMassBalanceAction::class);
        $recon = $reconcileAction->execute($run);
        $this->assertIsArray($recon);

        $auditAction = app(AuditSmelterChainOfCustodyAction::class);
        $audit = $auditAction->execute($run);
        $this->assertIsArray($audit);

        // Ekspor CSV
        $exportAction = app(ExportSmelterTradingReportCsvAction::class);
        $csv = $exportAction->execute();
        $this->assertGreaterThan(0, $csv['rows_count']);

        // Abort action test
        $abortRun = MiningSmelterFurnaceRun::create(['furnace_id' => $furnace->id, 'run_code' => 'RUN-ABORT-01', 'status' => 'DRAFT']);
        $aborted = app(AbortSmelterFurnaceRunAction::class)->execute($abortRun, 'Test abort');
        $this->assertEquals(FurnaceRunStatus::ABORTED->value, $aborted->status);
    }
}
