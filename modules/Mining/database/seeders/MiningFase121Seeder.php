<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Mining\Application\Actions\ConductSmelterQualityAssayAction;
use Modules\Mining\Application\Actions\CreateMetalsTradingContractAction;
use Modules\Mining\Application\Actions\CreateSmelterFurnaceRunAction;
use Modules\Mining\Application\Actions\DispatchSmelterByproductSlagAction;
use Modules\Mining\Application\Actions\ExecuteLmeMarkToMarketValuationAction;
use Modules\Mining\Application\Actions\ExecuteSmelterEnergyBalanceAction;
use Modules\Mining\Application\Actions\IssueMetalsWarehouseReceiptAction;
use Modules\Mining\Application\Actions\PledgeWarehouseReceiptBankScfAction;
use Modules\Mining\Application\Actions\RegisterWarehouseReceiptBappebtiAction;
use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningSmelterFurnace;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;

/**
 * Seeder T1 Kaya Modul Pertambangan Fase 121 (Standar W8).
 * Menginisialisasi 16 skenario bernama idempoten mencakup:
 * - Tanur Peleburan Smelter RKEF & HPAL Autoclave
 * - Siklus Batch Run Tanur (DRAFT, CHARGING, SMELTING, TAPPING, COMPLETED, ABORTED)
 * - Emisi Energi Scope 1/2 & Solver Neraca Termal Pyrometallurgical
 * - Uji Mutu Laboratorium Spektrometri Spek LME
 * - Kontrak Penjualan Fisik & Lindung Nilai LME
 * - Valuasi Mark-to-Market Harian & Margin Call
 * - Resi Gudang Digital SRG ISWARE Bappebti & Agunan Bank SCF
 * - Produk Sampingan Terak Slag & Manifest SIMPEL KLHK
 */
class MiningFase121Seeder extends Seeder
{
    public function run(): void
    {
        // Pastikan akun buku besar terdaftar
        $this->call(LedgerAccountsSeeder::class);

        $site = MiningSite::first();
        $siteId = $site ? (string) $site->id : null;

        // 1. Seed Tanur Peleburan (Skenario 1 - 4)
        $f1 = MiningSmelterFurnace::updateOrCreate(
            ['furnace_code' => 'FURN-RKEF-01'],
            [
                'site_id' => $siteId,
                'name' => 'Submerged Arc Electric Furnace Unit 1 (RKEF 45 MVA)',
                'furnace_type' => 'RKEF_ELECTRIC_ARC',
                'rated_capacity_tons_day' => 600.0,
                'transformer_power_mva' => 45.0,
                'operating_temperature_celsius' => 1550.0,
                'electrode_diameter_mm' => 1250.0,
                'target_metal' => 'NICKEL_PIG_IRON',
                'status' => 'OPERATIONAL',
            ]
        );

        $f2 = MiningSmelterFurnace::updateOrCreate(
            ['furnace_code' => 'FURN-RKEF-02'],
            [
                'site_id' => $siteId,
                'name' => 'Submerged Arc Electric Furnace Unit 2 (RKEF 45 MVA)',
                'furnace_type' => 'RKEF_ELECTRIC_ARC',
                'rated_capacity_tons_day' => 600.0,
                'transformer_power_mva' => 45.0,
                'operating_temperature_celsius' => 1550.0,
                'electrode_diameter_mm' => 1250.0,
                'target_metal' => 'NICKEL_PIG_IRON',
                'status' => 'OPERATIONAL',
            ]
        );

        $f3 = MiningSmelterFurnace::updateOrCreate(
            ['furnace_code' => 'FURN-ROTARY-KILN-01'],
            [
                'site_id' => $siteId,
                'name' => 'Pre-reduction Rotary Kiln Calciner Unit 1',
                'furnace_type' => 'ROTARY_KILN',
                'rated_capacity_tons_day' => 850.0,
                'transformer_power_mva' => 12.0,
                'operating_temperature_celsius' => 950.0,
                'electrode_diameter_mm' => 0.0,
                'target_metal' => 'CALCINED_ORE',
                'status' => 'OPERATIONAL',
            ]
        );

        $f4 = MiningSmelterFurnace::updateOrCreate(
            ['furnace_code' => 'FURN-HPAL-AUTO-01'],
            [
                'site_id' => $siteId,
                'name' => 'HPAL Titanium Autoclave Pressure Vessel 01',
                'furnace_type' => 'HPAL_AUTOCLAVE',
                'rated_capacity_tons_day' => 450.0,
                'transformer_power_mva' => 20.0,
                'operating_temperature_celsius' => 255.0,
                'electrode_diameter_mm' => 0.0,
                'target_metal' => 'MIXED_HYDROXIDE_PRECIPITATE',
                'status' => 'STANDBY',
            ]
        );

        // 2. Seed Batch Run Tanur (Skenario 5 - 9)
        $runAction = app(CreateSmelterFurnaceRunAction::class);
        $balanceAction = app(ExecuteSmelterEnergyBalanceAction::class);

        // Skenario 5: Batch Run Draft
        if (! MiningSmelterFurnaceRun::where('run_code', 'RUN-RKEF01-20261107-001')->exists()) {
            $runAction->execute((string) $f1->id, [
                'run_code' => 'RUN-RKEF01-20261107-001',
                'batch_lot_number' => 'LOT-20261107-A1',
                'ore_feed_wmt' => 125.0,
                'ore_moisture_pct' => 33.0,
                'ore_ni_grade_pct' => 1.82,
                'ore_fe_grade_pct' => 18.20,
                'coal_reductant_tons' => 15.0,
                'silica_flux_tons' => 8.5,
                'operator_in_charge' => 'Chief Metallurgist Bambang',
            ]);
        }

        // Skenario 6: Batch Run Charging
        $r2 = MiningSmelterFurnaceRun::where('run_code', 'RUN-RKEF01-20261107-002')->first();
        if (! $r2) {
            $r2 = $runAction->execute((string) $f1->id, [
                'run_code' => 'RUN-RKEF01-20261107-002',
                'batch_lot_number' => 'LOT-20261107-A2',
                'ore_feed_wmt' => 140.0,
                'ore_moisture_pct' => 31.5,
                'ore_ni_grade_pct' => 1.90,
                'ore_fe_grade_pct' => 17.80,
                'coal_reductant_tons' => 17.5,
                'silica_flux_tons' => 9.0,
            ]);
            $r2->status = FurnaceRunStatus::CHARGING->value;
            $r2->save();
        }

        // Skenario 7: Batch Run Smelting
        $r3 = MiningSmelterFurnaceRun::where('run_code', 'RUN-RKEF01-20261107-003')->first();
        if (! $r3) {
            $r3 = $runAction->execute((string) $f1->id, [
                'run_code' => 'RUN-RKEF01-20261107-003',
                'batch_lot_number' => 'LOT-20261107-A3',
                'ore_feed_wmt' => 150.0,
                'ore_moisture_pct' => 30.0,
                'ore_ni_grade_pct' => 1.88,
                'ore_fe_grade_pct' => 18.50,
                'coal_reductant_tons' => 18.0,
                'silica_flux_tons' => 10.0,
                'power_consumption_mwh' => 42.5,
            ]);
            $r3->status = FurnaceRunStatus::SMELTING->value;
            $r3->save();
        }

        // Skenario 8: Batch Run Completed dengan Neraca Terhitung
        $r4 = MiningSmelterFurnaceRun::where('run_code', 'RUN-RKEF01-20261107-004')->first();
        if (! $r4) {
            $r4 = $runAction->execute((string) $f1->id, [
                'run_code' => 'RUN-RKEF01-20261107-004',
                'batch_lot_number' => 'LOT-20261107-A4',
                'ore_feed_wmt' => 160.0,
                'ore_moisture_pct' => 30.0,
                'ore_ni_grade_pct' => 1.95,
                'ore_fe_grade_pct' => 19.00,
                'coal_reductant_tons' => 19.5,
                'silica_flux_tons' => 10.5,
                'power_consumption_mwh' => 52.0,
                'tapping_temperature_c' => 1535.0,
            ]);
            $balanceAction->execute($r4, 52.0);
            $r4->status = FurnaceRunStatus::COMPLETED->value;
            $r4->save();
        }

        // Skenario 9: Batch Run Aborted (Emergency Trip)
        $r5 = MiningSmelterFurnaceRun::where('run_code', 'RUN-RKEF02-20261107-005')->first();
        if (! $r5) {
            $r5 = $runAction->execute((string) $f2->id, [
                'run_code' => 'RUN-RKEF02-20261107-005',
                'batch_lot_number' => 'LOT-20261107-B1',
                'ore_feed_wmt' => 110.0,
                'ore_moisture_pct' => 34.0,
                'ore_ni_grade_pct' => 1.75,
                'ore_fe_grade_pct' => 19.20,
                'coal_reductant_tons' => 13.0,
                'silica_flux_tons' => 7.0,
            ]);
            $r5->status = FurnaceRunStatus::ABORTED->value;
            $r5->metadata = ['abort_reason' => 'Emergency water cooling pipe leakage in shell sector 3'];
            $r5->save();
        }

        // Skenario 10: Pengujian Mutu Assay Lab (ICP-OES / XRF)
        $assayAction = app(ConductSmelterQualityAssayAction::class);
        $assay = MiningSmelterQualityAssay::where('assay_number', 'ASY-NPI-BATCH-801')->first();
        if (! $assay && $r4) {
            $assay = $assayAction->execute($r4, [
                'assay_number' => 'ASY-NPI-BATCH-801',
                'sample_code' => 'SMP-RKEF-801-XRF',
                'metal_commodity' => 'NPI',
                'primary_metal_grade_pct' => 13.25,
                'iron_fe_pct' => 80.50,
                'carbon_c_pct' => 2.15,
                'silicon_si_pct' => 1.70,
                'phosphorus_p_pct' => 0.022,
                'sulfur_s_pct' => 0.030,
                'chief_chemist' => 'Chief Metallurgical Chemist Dr. Hendra',
            ]);
        }

        // Skenario 11 & 12: Kontrak Perdagangan Logam Fisik & Lindung Nilai LME
        $contractAction = app(CreateMetalsTradingContractAction::class);
        $c1 = MiningMetalsTradingContract::where('contract_number', 'CTR-GLENCORE-2026-01')->first();
        if (! $c1) {
            $c1 = $contractAction->execute([
                'contract_number' => 'CTR-GLENCORE-2026-01',
                'counterparty_name' => 'Glencore International AG',
                'metal_symbol' => 'LME-NI',
                'contract_type' => 'PHYSICAL_OFFTAKE',
                'contract_quantity_metric_tons' => 1500.0,
                'fixed_price_usd_cents_per_ton' => 1650000,
                'pricing_basis' => 'LME_CASH_MONTHLY_AVG',
            ]);
            $c1->status = TradingContractStatus::ACTIVE_HEDGED->value;
            $c1->save();
        }

        $c2 = MiningMetalsTradingContract::where('contract_number', 'CTR-TRAFIGURA-2026-02')->first();
        if (! $c2) {
            $c2 = $contractAction->execute([
                'contract_number' => 'CTR-TRAFIGURA-2026-02',
                'counterparty_name' => 'Trafigura Pte Ltd Singapore',
                'metal_symbol' => 'LME-NI',
                'contract_type' => 'HEDGE_FORWARD',
                'contract_quantity_metric_tons' => 1000.0,
                'fixed_price_usd_cents_per_ton' => 1680000,
                'pricing_basis' => 'LME_3M_SPREAD',
            ]);
            $c2->status = TradingContractStatus::PRICE_LOCKED->value;
            $c2->save();
        }

        // Skenario 13: Valuasi Harian Mark-to-Market
        if ($c1) {
            $mtmAction = app(ExecuteLmeMarkToMarketValuationAction::class);
            $mtmAction->execute($c1, 'SHORT', 1675000);
        }

        // Skenario 14 & 15: Resi Gudang Digital SRG ISWARE & Penjaminan Bank SCF
        $receiptAction = app(IssueMetalsWarehouseReceiptAction::class);
        $bappebtiAction = app(RegisterWarehouseReceiptBappebtiAction::class);
        $pledgeAction = app(PledgeWarehouseReceiptBankScfAction::class);

        if ($assay) {
            $srg1 = MiningMetalsWarehouseReceipt::where('receipt_number', 'SRG-NPI-BAPP-001')->first();
            if (! $srg1) {
                $srg1 = $receiptAction->execute($assay, 250.0, [
                    'receipt_number' => 'SRG-NPI-BAPP-001',
                    'warehouse_code' => 'WMS-BAPPEBTI-01',
                    'unit_market_price_idr_per_ton' => 245000000,
                    'storage_location_rack' => 'ZONE-A-RACK-01',
                ]);
                $bappebtiAction->execute($srg1);
                $pledgeAction->execute($srg1, 'PT Bank Mandiri (Persero) Tbk');
            }

            $srg2 = MiningMetalsWarehouseReceipt::where('receipt_number', 'SRG-NPI-BAPP-002')->first();
            if (! $srg2) {
                $srg2 = $receiptAction->execute($assay, 150.0, [
                    'receipt_number' => 'SRG-NPI-BAPP-002',
                    'warehouse_code' => 'WMS-BAPPEBTI-02',
                    'unit_market_price_idr_per_ton' => 245000000,
                    'storage_location_rack' => 'ZONE-B-RACK-04',
                ]);
                $bappebtiAction->execute($srg2);
            }
        }

        // Skenario 16: Pengiriman Produk Sampingan Terak Slag & Manifest KLHK
        if ($r4) {
            $dispatchAction = app(DispatchSmelterByproductSlagAction::class);
            $dispatchAction->execute($r4, 85.0, [
                'byproduct_code' => 'SLG-DISPATCH-2026-01',
                'buyer_party_code' => 'PT Semen Tonasa Aggregate Desk',
                'unit_price_idr_per_ton' => 85000,
                'utilization_application' => 'Eco-Friendly Geopolymer Road Subbase',
            ]);
        }
    }
}
