<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\AdjustCoalCommercialPriceAction;
use Modules\Mining\Application\Actions\AuditMiningConcessionPermitsAction;
use Modules\Mining\Application\Actions\AuditTailingsDamSafetyAction;
use Modules\Mining\Application\Actions\CalculateVerifiedRoyaltyAction;
use Modules\Mining\Application\Actions\CalibrateWeighbridgeScaleAction;
use Modules\Mining\Application\Actions\EvaluateBlastingVibrationAction;
use Modules\Mining\Application\Actions\ExecuteSmelterRunAction;
use Modules\Mining\Application\Actions\IngestEnvironmentalSensorReadingAction;
use Modules\Mining\Application\Actions\IngestMineGasReadingAction;
use Modules\Mining\Application\Actions\IssueHighRiskWorkPermitAction;
use Modules\Mining\Application\Actions\NominateOfftakeVesselAction;
use Modules\Mining\Application\Actions\OptimizeOreBlendingAction;
use Modules\Mining\Application\Actions\ReconcileBargeDraftSurveyAction;
use Modules\Mining\Application\Actions\ReconcileMoistureLossAction;
use Modules\Mining\Application\Actions\RecordHashChainWeighbridgeTicketAction;
use Modules\Mining\Application\Actions\RunMetallurgicalMassBalanceReconciliationAction;
use Modules\Mining\Application\Actions\ScanExpiringPermitsAction;
use Modules\Mining\Application\Actions\ScheduleMineReclamationAction;
use Modules\Mining\Application\Actions\SettleFinalAssayOfftakeInvoiceAction;
use Modules\Mining\Application\Actions\SettleProvisionalOfftakeInvoiceAction;
use Modules\Mining\Application\Actions\TrackMineReserveDepletionAction;
use Modules\Mining\Application\Actions\UnwindReclamationProvisionAction;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Models\MiningConcessionPermit;
use Modules\Mining\Domain\Models\MiningExternalFactor;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Mining\Domain\Models\MiningReferencePrice;
use Modules\Mining\Domain\Models\MiningRoyaltyRateVersion;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Domain\Services\GySamplingTheoryEvaluator;
use Modules\Mining\Domain\Services\MineAcidDrainagePredictor;
use Modules\Mining\Domain\Services\MineCarbonEmissionCalculator;
use Modules\Mining\Domain\Services\MineDemurrageDespatchCalculator;
use Modules\Mining\Domain\Services\MineSlopeStabilityAnalyzer;
use Modules\Mining\Domain\Services\SmelterEnergyBalanceCalculator;
use Modules\Mining\Domain\Services\WeighbridgeAnomalyDetector;
use Modules\Mining\Domain\StateMachines\OfftakeShipmentStateMachine;

/**
 * Seeder T1 Kaya Modul Pertambangan Fase 94 (D94-1..D94-10, Standar W8).
 *
 * Menginisialisasi:
 * - 8 Jembatan Timbang & Audit Rantai Hash
 * - 60 Stockpile ROM, Blending & Port Jetty
 * - 12 Kontrak Offtake (Ekspor & Domestik)
 * - 6 Bulan Royalti Progresif (PP 19/2025)
 * - 15 Kasus Bernama Komprehensif
 * - Diuji dalam 2 Dunia: Dunia Normal & Dunia Kejutan (Shock)
 */
class MiningFase94Seeder extends Seeder
{
    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);

        // 1. Konsesi Tambang (Batubara Kaltim & Nikel Sultra)
        $siteCoal = MiningSite::firstOrCreate(
            ['site_code' => 'SITE-COAL-KALTIM-01'],
            [
                'name' => 'Konsesi Batubara Kutai Timur Mandiri',
                'commodity' => 'COAL',
                'location' => 'Kutai Timur, Kalimantan Timur',
                'timezone' => 'Asia/Makassar',
                'status' => 'ACTIVE',
            ]
        );

        $siteNickel = MiningSite::firstOrCreate(
            ['site_code' => 'SITE-NICKEL-KONAWE-01'],
            [
                'name' => 'Konsesi Nikel Laterit Konawe Gemilang',
                'commodity' => 'NICKEL',
                'location' => 'Konawe Utara, Sulawesi Tenggara',
                'timezone' => 'Asia/Makassar',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Regulasi Tarif Royalti PP 19/2025 Lampiran I (M4)
        $this->seedRoyaltyRateVersions();

        // 3. Harga Acuan HBA & HMA 6 Bulan Terakhir
        $this->seedReferencePrices();

        // 4. Izin Konsesi Pertambangan (IUP OP, IPPKH, AMDAL, RKAB)
        $this->seedConcessionPermits($siteCoal, $siteNickel);

        // 5. Pembuatan 60 Stockpile (30 Batubara, 30 Nikel)
        $stockpiles = $this->seedStockpiles($siteCoal, $siteNickel);

        // 6. 12 Kontrak Offtake (Domestik Smelter & Ekspor)
        $contracts = $this->seedOfftakeContracts($siteCoal, $siteNickel);

        // 7. Eksekusi 15 Kasus Bernama dalam 2 Dunia (Dunia Normal & Dunia Kejutan)
        $this->runNamedTestCases($siteCoal, $siteNickel, $stockpiles, $contracts);
    }

    private function seedRoyaltyRateVersions(): void
    {
        $versions = [
            // Batubara Open Pit (PP 19/2025)
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 4200, 'p_min' => 0, 'p_max' => 6999, 'rate' => 500, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 4200, 'p_min' => 7000, 'p_max' => 8999, 'rate' => 600, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 4200, 'p_min' => 9000, 'p_max' => 99999, 'rate' => 900, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 4201, 'c_max' => 5200, 'p_min' => 0, 'p_max' => 6999, 'rate' => 700, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 4201, 'c_max' => 5200, 'p_min' => 7000, 'p_max' => 8999, 'rate' => 850, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 4201, 'c_max' => 5200, 'p_min' => 9000, 'p_max' => 99999, 'rate' => 1150, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 5201, 'c_max' => 99999, 'p_min' => 0, 'p_max' => 6999, 'rate' => 950, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 5201, 'c_max' => 99999, 'p_min' => 7000, 'p_max' => 8999, 'rate' => 1150, 'source' => 'PP 19/2025 Lampiran I'],
            ['commodity' => 'COAL', 'product' => 'RAW_COAL', 'method' => 'OPEN_PIT', 'c_min' => 5201, 'c_max' => 99999, 'p_min' => 9000, 'p_max' => 99999, 'rate' => 1350, 'source' => 'PP 19/2025 Lampiran I'],
            // Bijih Nikel Laterit
            ['commodity' => 'NICKEL', 'product' => 'NICKEL_ORE', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 0, 'p_min' => 0, 'p_max' => 1799999, 'rate' => 1400, 'source' => 'PP 19/2025 Tarif Mineral Logam'],
            ['commodity' => 'NICKEL', 'product' => 'NICKEL_ORE', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 0, 'p_min' => 1800000, 'p_max' => 9999999, 'rate' => 1600, 'source' => 'PP 19/2025 Tarif Mineral Logam'],
            ['commodity' => 'NICKEL', 'product' => 'FERRONICKEL', 'method' => 'OPEN_PIT', 'c_min' => 0, 'c_max' => 0, 'p_min' => 0, 'p_max' => 9999999, 'rate' => 200, 'source' => 'PP 19/2025 Produk Hilir Smelter'],
        ];

        foreach ($versions as $idx => $v) {
            MiningRoyaltyRateVersion::firstOrCreate(
                [
                    'commodity' => $v['commodity'],
                    'product_type' => $v['product'],
                    'mining_method' => $v['method'],
                    'calorific_min_kcal' => $v['c_min'],
                    'calorific_max_kcal' => $v['c_max'],
                    'price_tier_min_usd_cents' => $v['p_min'],
                    'price_tier_max_usd_cents' => $v['p_max'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'version_tag' => 'PP19_2025_TIER_'.($idx + 1),
                    'version' => 1,
                    'rate_bps' => $v['rate'],
                    'effective_from' => '2025-04-26',
                    'source' => $v['source'],
                    'label' => 'regulasi',
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedReferencePrices(): void
    {
        $months = ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'];
        foreach ($months as $m) {
            // HBA Batubara
            MiningReferencePrice::firstOrCreate(
                ['commodity' => 'COAL', 'price_type' => 'HBA', 'period_date' => "{$m}-01"],
                [
                    'id' => (string) Str::uuid(),
                    'period_phase' => 1,
                    'price_cents' => 8500, // USD 85.00
                    'currency' => 'USD',
                    'source' => 'Kepmen ESDM Penetapan HBA',
                    'published_at' => "{$m}-01 00:00:00",
                ]
            );

            // HMA Nikel
            MiningReferencePrice::firstOrCreate(
                ['commodity' => 'NICKEL', 'price_type' => 'HMA', 'period_date' => "{$m}-01"],
                [
                    'id' => (string) Str::uuid(),
                    'period_phase' => 1,
                    'price_cents' => 1650000, // USD 16,500 / ton dmt
                    'currency' => 'USD',
                    'source' => 'Kepmen ESDM Penetapan HMA Mineral',
                    'published_at' => "{$m}-01 00:00:00",
                ]
            );
        }
    }

    private function seedConcessionPermits(MiningSite $siteCoal, MiningSite $siteNickel): void
    {
        $permits = [
            ['site_id' => $siteCoal->id, 'code' => 'IUP-OP-COAL-001', 'type' => 'IUP_OP', 'auth' => 'Ditjen Minerba ESDM', 'until' => '2035-12-31'],
            ['site_id' => $siteCoal->id, 'code' => 'PPKH-COAL-002', 'type' => 'PPKH_FOREST', 'auth' => 'Kementerian LHK', 'until' => '2030-06-30'],
            ['site_id' => $siteCoal->id, 'code' => 'AMDAL-COAL-003', 'type' => 'AMDAL_ENV', 'auth' => 'Dinas Lingkungan Hidup Kaltim', 'until' => '2035-12-31'],
            ['site_id' => $siteCoal->id, 'code' => 'RKAB-COAL-2026', 'type' => 'RKAB_ANNUAL', 'auth' => 'Ditjen Minerba ESDM', 'until' => '2026-12-31'],
            ['site_id' => $siteNickel->id, 'code' => 'IUP-OP-NI-001', 'type' => 'IUP_OP', 'auth' => 'Ditjen Minerba ESDM', 'until' => '2038-08-17'],
            ['site_id' => $siteNickel->id, 'code' => 'PPKH-NI-002', 'type' => 'PPKH_FOREST', 'auth' => 'Kementerian LHK', 'until' => '2032-12-31'],
            ['site_id' => $siteNickel->id, 'code' => 'RKAB-NI-2026', 'type' => 'RKAB_ANNUAL', 'auth' => 'Ditjen Minerba ESDM', 'until' => '2026-12-31'],
        ];

        foreach ($permits as $p) {
            MiningConcessionPermit::firstOrCreate(
                ['permit_number' => $p['code']],
                [
                    'id' => (string) Str::uuid(),
                    'site_id' => $p['site_id'],
                    'permit_type' => $p['type'],
                    'concession_area_hectares' => 5000,
                    'issued_date' => '2020-01-01',
                    'expiry_date' => $p['until'],
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * @return list<MiningStockpile>
     */
    private function seedStockpiles(MiningSite $siteCoal, MiningSite $siteNickel): array
    {
        $stockpiles = [];

        // 30 Stockpile Batubara (ROM Pit, Crushed, Port Stockpile)
        for ($i = 1; $i <= 30; $i++) {
            $code = sprintf('STK-COAL-%02d', $i);
            $stk = MiningStockpile::firstOrNew(['stockpile_code' => $code]);
            if (! $stk->exists) {
                $stk->id = (string) Str::uuid();
            }
            $stk->fill([
                'site_id' => $siteCoal->id,
                'name' => "Stockpile Batubara Area {$i}",
                'total_kg' => 15000000 + ($i * 500000), // 15.000 - 30.000 ton
                'sum_kg_calories' => (15000000 + ($i * 500000)) * (4800 + ($i * 20)),
                'sum_kg_ash_bps' => (15000000 + ($i * 500000)) * 850,
                'sum_kg_sulfur_bps' => (15000000 + ($i * 500000)) * 80,
                'sum_kg_moisture_bps' => (15000000 + ($i * 500000)) * 1400,
                'avg_grade_pct' => 50.0,
            ])->save();
            $stockpiles[] = $stk;
        }

        // 30 Stockpile Nikel (Limonite, Low-Saprolite, High-Saprolite, Smelter Blend)
        for ($i = 1; $i <= 30; $i++) {
            $code = sprintf('STK-NI-%02d', $i);
            $stk = MiningStockpile::firstOrNew(['stockpile_code' => $code]);
            if (! $stk->exists) {
                $stk->id = (string) Str::uuid();
            }
            $stk->fill([
                'site_id' => $siteNickel->id,
                'name' => "Stockpile Nikel Laterit {$i}",
                'total_kg' => 10000000 + ($i * 400000),
                'sum_kg_calories' => 0,
                'sum_kg_ash_bps' => 0,
                'sum_kg_sulfur_bps' => 0,
                'sum_kg_moisture_bps' => (10000000 + ($i * 400000)) * 3200,
                'sum_kg_metal_bps' => (10000000 + ($i * 400000)) * (160 + ($i * 2)), // 1.6% - 2.2% Ni
                'avg_grade_pct' => 1.8,
            ])->save();
            $stockpiles[] = $stk;
        }

        return $stockpiles;
    }

    /**
     * @return list<MiningOfftakeContract>
     */
    private function seedOfftakeContracts(MiningSite $siteCoal, MiningSite $siteNickel): array
    {
        $contracts = [];

        for ($i = 1; $i <= 12; $i++) {
            $isCoal = ($i <= 6);
            $isExport = ($i % 2 === 0);
            $code = sprintf('CNT-OFT-2026-%02d', $i);

            $contracts[] = MiningOfftakeContract::firstOrCreate(
                ['contract_code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'site_id' => $isCoal ? $siteCoal->id : $siteNickel->id,
                    'contract_number' => $code,
                    'buyer_party_ref' => 'BUYER-PARTNER-'.($i + 100),
                    'party_ref' => 100 + $i,
                    'commodity' => $isCoal ? 'COAL' : 'NICKEL',
                    'destination_type' => $isExport ? 'EXPORT' : 'DOMESTIC',
                    'pricing_formula' => 'INDEX_ADJUSTED_PP19',
                    'base_price_cents' => $isCoal ? 8500 : 1550000,
                    'base_price_usd_cents' => $isCoal ? 8500 : 1550000,
                    'target_volume_tons' => 100000,
                    'contracted_tonnage_kg' => 100000000,
                    'fulfilled_volume_tons' => 0,
                    'benchmark_gar_kcal' => 5000,
                    'lc_required' => $isExport,
                    'status' => 'ACTIVE',
                ]
            );
        }

        return $contracts;
    }

    /**
     * Menjalankan 15 Kasus Bernama dalam 2 Dunia (Normal & Shock).
     *
     * @param  list<MiningStockpile>  $stockpiles
     * @param  list<MiningOfftakeContract>  $contracts
     */
    private function runNamedTestCases(
        MiningSite $siteCoal,
        MiningSite $siteNickel,
        array $stockpiles,
        array $contracts
    ): void {
        $ticketAction = app(RecordHashChainWeighbridgeTicketAction::class);
        $reconcileAction = app(RunMetallurgicalMassBalanceReconciliationAction::class);
        $royaltyAction = app(CalculateVerifiedRoyaltyAction::class);
        $blendingAction = app(OptimizeOreBlendingAction::class);
        $coalPriceAction = app(AdjustCoalCommercialPriceAction::class);
        $smelterAction = app(ExecuteSmelterRunAction::class);
        $envSensorAction = app(IngestEnvironmentalSensorReadingAction::class);
        $workPermitAction = app(IssueHighRiskWorkPermitAction::class);
        $scanPermitsAction = app(ScanExpiringPermitsAction::class);
        $auditPermitsAction = app(AuditMiningConcessionPermitsAction::class);
        $reclamationScheduleAction = app(ScheduleMineReclamationAction::class);
        $reclamationUnwindAction = app(UnwindReclamationProvisionAction::class);
        $nominateVesselAction = app(NominateOfftakeVesselAction::class);
        $provisionalInvoiceAction = app(SettleProvisionalOfftakeInvoiceAction::class);
        $finalAssaySettleAction = app(SettleFinalAssayOfftakeInvoiceAction::class);

        // Bersihkan data seed dinamis agar seeder idempoten jika dijalankan berulang
        WeighbridgeTicket::whereIn('truck_plate_number', ['KT-8801-AA', 'DT-7701-BB', 'KT-8802-AC', 'DT-7702-BC'])
            ->orWhereIn('vehicle_plate', ['KT-8801-AA', 'DT-7701-BB'])
            ->delete();
        MiningReconciliationRun::where('site_id', (string) $siteNickel->id)->delete();
        MiningRoyaltyReturn::where('site_id', (string) $siteCoal->id)->delete();
        MiningWorkPermit::where('site_id', (string) $siteCoal->id)->delete();
        MiningOfftakeShipment::where('contract_id', (string) $contracts[0]->id)->delete();

        // ==========================================
        // DUNIA NORMAL (Cases 1 - 10)
        // ==========================================

        // Kasus 1: T1-WB-COAL-NORMAL-01 (Penimbangan Tiket Batubara Rantai-Hash)
        $t1 = $ticketAction->execute([
            'site_id' => (string) $siteCoal->id,
            'stockpile_id' => (string) $stockpiles[0]->id,
            'vehicle_plate' => 'KT-8801-AA',
            'gross_weight_kg' => 75000,
            'tare_weight_kg' => 25000,
            'weighed_at' => '2026-11-01 08:00:00',
            'destination' => 'STOCKPILE_IN',
        ]);

        // Kasus 2: T1-WB-NICKEL-NORMAL-02 (Penimbangan Tiket Nikel Laterit)
        $t2 = $ticketAction->execute([
            'site_id' => (string) $siteNickel->id,
            'stockpile_id' => (string) $stockpiles[30]->id,
            'vehicle_plate' => 'DT-7701-BB',
            'gross_weight_kg' => 60000,
            'tare_weight_kg' => 20000,
            'weighed_at' => '2026-11-01 08:30:00',
            'destination' => 'STOCKPILE_IN',
        ]);

        // Kasus 3: T1-REC-MASS-BALANCE-03 (Rekonsiliasi Neraca Massa M5)
        $reconcileAction->execute([
            'site_id' => (string) $siteNickel->id,
            'period_month' => '2026-10',
            'feed_metal_kg' => 24000,
            'concentrate_metal_kg' => 19200,
            'tailings_metal_kg' => 4700,
            'feed_variance_weight' => 4,
            'concentrate_variance_weight' => 1,
            'tailings_variance_weight' => 3,
            'tolerance_kg' => 150,
        ]);

        // Kasus 4: T1-ROY-PP19-NORMAL-04 (Akrual Royalti Terverifikasi M4 Batubara)
        $royaltyAction->execute([
            'site_id' => (string) $siteCoal->id,
            'period_month' => '2026-10',
            'commodity' => 'COAL',
            'product_type' => 'RAW_COAL',
            'mining_method' => 'OPEN_PIT',
            'calorific_kcal' => 5000,
            'reference_price_usd_cents' => 8500,
            'benchmark_invoice_price_usd_cents' => 7500,
            'exchange_rate_idr' => 16250,
            'production_date' => '2026-10-31',
            'idempotency_key' => 'min:royalty-calc:COAL-OCT:2026-10',
        ]);

        // Kasus 5: T1-BLEND-MULTI-STK-05 (Optimasi Blending Batubara Ekspor)
        $blendingAction->execute([
            'target_tonnage' => 5000,
            'target_gar_kcal' => 5000,
            'max_ash_pct' => 9.5,
            'max_sulfur_pct' => 0.9,
            'candidate_stockpile_ids' => [
                (string) $stockpiles[0]->id,
                (string) $stockpiles[1]->id,
                (string) $stockpiles[2]->id,
                (string) $stockpiles[3]->id,
            ],
        ]);

        // Kasus 6: T1-PRICE-ADJUST-M6-06 (Penyesuaian Harga Komersial GAR/Ash/Sulfur M6)
        $coalPriceAction->execute([
            'base_price_cents' => 8500,
            'actual_gar_kcal' => 4850,
            'reference_gar_kcal' => 5000,
            'actual_ash_permil' => 93,
            'base_ash_limit_permil' => 100,
            'actual_sulfur_permil' => 9,
            'base_sulfur_limit_permil' => 10,
            'volume_tons' => 55000,
        ]);

        // Kasus 7: T1-SML-RKEF-FENI-07 (Kampanye Peleburan Smelter FeNi G7-K12)
        $smelterAction->execute([
            'site_id' => (string) $siteNickel->id,
            'stockpile_id' => (string) $stockpiles[30]->id,
            'feed_tonnage_kg' => 500000,
            'target_product' => 'FERRONICKEL',
            'metallurgical_recovery_bps' => 9300,
            'electric_power_kwh_per_ton' => 420,
        ]);

        // Kasus 8: T1-PTW-BLASTING-K3-08 (Penerbitan Izin Kerja Peledakan High-Risk)
        $workPermitAction->execute([
            'site_id' => (string) $siteCoal->id,
            'worker_id' => 'KTT-BLASTER-01',
            'activity_type' => 'BLASTING',
            'geofence_zone' => 'PIT-MAHAKAM-NORTH',
            'risk_level' => 'critical',
            'valid_hours' => 6,
            'supervisor_name' => 'KTT Ir. Bambang P. S.',
        ]);

        // Kasus 9: T1-RECLAM-SCHEDULE-09 (Jadwal Provisi Reklamasi Lereng Tambang)
        $reclamationScheduleAction->execute([
            'site_id' => (string) $siteCoal->id,
            'area_name' => 'Pit Alpha Revegetasi Zona 1',
            'target_hectares' => 15.5,
            'planned_year' => 2026,
            'planned_quarter' => 4,
            'estimated_cost_idr' => 750000000,
            'escrow_bank' => 'Bank Mandiri Escrow Reklamasi',
        ]);

        // Kasus 10: T1-OFT-SHIPMENT-FLOW-10 (Nominasi Kapal & Invoice Provisional G7-K11)
        $shipment = $nominateVesselAction->execute([
            'contract_id' => (string) $contracts[0]->id,
            'vessel_name' => 'MV SRIWIJAYA PRIDE',
            'laycan_start' => '2026-11-15',
            'laycan_end' => '2026-11-18',
            'nominated_volume_tons' => 50000,
        ]);
        $shipmentSM = new OfftakeShipmentStateMachine;
        $shipmentSM->transition($shipment, OfftakeShipmentStatus::LC_OPENED, 'trade_finance', 'LC terbit via G7-K11');
        $shipmentSM->transition($shipment, OfftakeShipmentStatus::LOADING, 'dock_master', 'Tongkang mulai dimuat');

        $provisionalInvoiceAction->execute([
            'shipment_id' => (string) $shipment->id,
            'provisional_percentage_bps' => 8500,
            'exchange_rate_idr' => 16250,
        ]);

        // Kalkulasi Demurrage & Despatch Pengapalan Tongkang (D94-7)
        $demurrageCalc = new MineDemurrageDespatchCalculator;
        $demurrageCalc->calculate([
            'contracted_volume_tons' => 50000,
            'loading_rate_tons_per_day' => 12500,
            'demurrage_rate_usd_per_day' => 15000,
            'despatch_rate_usd_per_day' => 7500,
            'laycan_start' => '2026-11-15 08:00:00',
            'notice_of_readiness_tendered' => '2026-11-15 08:00:00',
            'loading_commenced' => '2026-11-15 14:00:00',
            'loading_completed' => '2026-11-18 18:00:00',
            'weather_downtime_hours' => 6,
        ]);

        // ==========================================
        // DUNIA KEJUTAN / SHOCK CASES (Cases 11 - 15)
        // ==========================================

        // Kasus 11: T1-SHOCK-EXPORT-BAN-11 (Regulasi Larangan Ekspor Bijih Mentah Nikel)
        MiningExternalFactor::firstOrCreate(
            ['factor_key' => 'export_policy_NICKEL'],
            [
                'id' => (string) Str::uuid(),
                'version' => 1,
                'numeric_value_scaled' => 1, // Ekspor dilarang
                'unit' => 'boolean',
                'effective_from' => '2026-11-01',
                'source' => 'Permen ESDM Larangan Ekspor Bijih Mentah Hilirisasi',
                'label' => 'regulasi',
            ]
        );

        // Kasus 12: T1-SHOCK-ENV-BREACH-12 (Pelanggaran Baku Mutu Air Asam Tambang)
        $envSensorAction->execute([
            'site_id' => (string) $siteCoal->id,
            'sensor_point_code' => 'SP-SEDIMENT-POND-03',
            'reading_type' => 'WATER_SEDIMENT_POND',
            'ph_scaled' => 420, // pH 4.20 asam ekstrem (batas: 6.00 - 9.00)
            'tss_mg_l_scaled' => 45000, // TSS 450 mg/L (batas: 300 mg/L)
            'fe_mg_l_scaled' => 1250, // Fe 12.5 mg/L (batas: 7.00 mg/L)
            'mn_mg_l_scaled' => 680, // Mn 6.8 mg/L (batas: 4.00 mg/L)
            'read_at' => '2026-11-07 07:00:00',
        ]);

        // Kasus 13: T1-SHOCK-PERMIT-EXPIRY-13 (Pemindaian Izin Kerja Kedaluwarsa)
        $scanPermitsAction->execute(CarbonImmutable::parse('2026-11-07 23:59:59'));

        // Kasus 14: T1-SHOCK-AUDIT-PERMITS-14 (Audit Kepatuhan Izin Konsesi IUP OP / RKAB)
        $auditPermitsAction->execute((string) $siteCoal->id);

        // Kasus 15: T1-SHOCK-ACID-ROCK-15 (Prediksi Batuan PAF & Dosis Kapur Tohor)
        $acidPredictor = new MineAcidDrainagePredictor;
        $acidPredictor->evaluate([
            'total_sulfur_pct' => 2.4, // MPA = 73.44 kg H2SO4/ton
            'anc_kg_h2so4_per_ton' => 12.0, // NAPP = +61.44 (PAF tinggi)
            'nag_ph' => 3.2,
            'rock_sample_code' => 'RCK-CORE-OB-9499',
        ]);

        // Evaluasi Emisi Karbon Tambang
        $carbonCalc = new MineCarbonEmissionCalculator;
        $carbonCalc->calculate([
            'diesel_consumed_liters' => 1200000,
            'electricity_consumed_kwh' => 2500000,
            'explosives_anfo_kg' => 85000,
            'total_production_tons' => 850000,
        ]);

        // Kasus Tambahan: Kalibrasi Tera Jembatan Timbang & Deteksi Anomali
        app(CalibrateWeighbridgeScaleAction::class)->execute([
            'site_id' => (string) $siteCoal->id,
            'scale_device_code' => 'WB-SCALE-KALTIM-01',
            'test_weight_standard_kg' => 60000,
            'indicated_weight_kg' => 60005,
            'technician_license_number' => 'TERA-METROLOGI-2026-94',
        ]);

        $wbDetector = new WeighbridgeAnomalyDetector;
        $wbDetector->analyze([
            'truck_plate' => 'KT-8801-AA',
            'truck_type' => 'CAT_777D',
            'rated_payload_ton' => 95.0,
            'historical_avg_tare_kg' => 25000,
            'current_tare_kg' => 25200,
            'current_gross_kg' => 75200,
        ]);

        // Evaluasi Teori Sampling Pierre Gy
        $gyEvaluator = new GySamplingTheoryEvaluator;
        $gyEvaluator->evaluate([
            'lot_mass_kg' => 50000000.0,
            'sample_mass_kg' => 120.0,
            'top_size_d95_mm' => 50.0,
        ]);

        // Rekonsiliasi Susut Kelembaban Kargo
        app(ReconcileMoistureLossAction::class)->execute([
            'site_id' => (string) $siteCoal->id,
            'stockpile_id' => (string) $stockpiles[0]->id,
            'dispatched_gross_tonnage' => 50000,
            'origin_total_moisture_pct' => 14.5,
            'destination_total_moisture_pct' => 13.8,
            'settlement_price_usd_cents' => 8500,
            'exchange_rate_idr' => 16250,
        ]);

        // Pemantauan Gas K3 Tambang & Kestabilan Lereng Geoteknik
        app(IngestMineGasReadingAction::class)->execute([
            'site_id' => (string) $siteCoal->id,
            'sensor_location_code' => 'PIT-MAHAKAM-NORTH-SECTOR-2',
            'o2_pct_scaled' => 2050,
            'co_ppm' => 12,
            'ch4_ppm' => 120,
        ]);

        $slopeAnalyzer = new MineSlopeStabilityAnalyzer;
        $slopeAnalyzer->analyze([
            'pit_bench_code' => 'BNCH-MAHAKAM-E45',
            'slope_type' => 'OVERALL_SLOPE',
            'slope_angle_degrees' => 42.0,
            'slope_height_meters' => 120.0,
            'cohesion_kpa' => 45.0,
            'friction_angle_degrees' => 32.0,
            'unit_weight_kn_m3' => 22.5,
        ]);

        // Neraca Termal Smelter
        $energyCalc = new SmelterEnergyBalanceCalculator;
        $energyCalc->calculate([
            'dry_feed_tonnage' => 5000,
            'moisture_content_pct' => 32.0,
            'coal_reductant_rate_kg_per_ton' => 95,
            'coal_calorific_kcal' => 5800,
            'electric_power_kwh_per_ton' => 450,
        ]);

        // Unwinding Provisi Reklamasi
        $reclamationUnwindAction->execute([
            'site_id' => (string) $siteCoal->id,
            'year' => 2026,
            'quarter' => 4,
            'discount_rate_bps' => 650,
        ]);

        // Audit Keamanan Bendungan Tailing TSF (GISTM)
        app(AuditTailingsDamSafetyAction::class)->execute([
            'site_id' => (string) $siteNickel->id,
            'dam_code' => 'TSF-NICKEL-KONAWE-01',
            'crest_elevation_meters' => 125.0,
            'pond_water_elevation_meters' => 123.2,
            'phreatic_line_ratio' => 0.35,
            'toe_seepage_flow_liters_per_minute' => 15.0,
            'historic_baseline_seepage_lpm' => 14.2,
            'cyclic_shear_stress_ratio' => 0.18,
            'cyclic_resistance_ratio' => 0.28,
        ]);

        // Rekonsiliasi Sarat Tongkang (Draft Survey Pelabuhan)
        app(ReconcileBargeDraftSurveyAction::class)->execute([
            'shipment_id' => (string) $shipment->id,
            'barge_code' => 'BG-KALTIM-3301',
            'weighbridge_total_tons' => 50000,
            'initial_fwd_draft_m' => 0.85,
            'initial_mid_draft_m' => 0.90,
            'initial_aft_draft_m' => 0.95,
            'final_fwd_draft_m' => 4.75,
            'final_mid_draft_m' => 4.82,
            'final_aft_draft_m' => 4.88,
            'measured_water_density_g_cm3' => 1.015,
            'tons_per_centimeter_immersion_tpc' => 12.8,
        ]);

        // Pelacak Deplesi & Rekonsiliasi Cadangan Tambang KCMI/JORC
        app(TrackMineReserveDepletionAction::class)->execute([
            'site_id' => (string) $siteCoal->id,
            'pit_code' => 'PIT-MAHAKAM-NORTH',
            'initial_proved_reserves_tons' => 12500000,
            'initial_probable_reserves_tons' => 25000000,
            'mined_production_tons' => 850000,
            'exploration_conversion_tons' => 1200000,
            'actual_overburden_bcm' => 5100000,
            'planned_life_of_mine_sr' => 5.5,
            'stripping_cost_per_bcm_idr' => 38000,
        ]);

        // Pemantau Getaran Seismik Peledakan & Airblast (SNI 7571:2010)
        app(EvaluateBlastingVibrationAction::class)->execute([
            'site_id' => (string) $siteCoal->id,
            'blast_event_id' => 'BLAST-202611-001',
            'distance_meters' => 450.0,
            'charge_per_delay_kg' => 180.0,
            'dominant_frequency_hz' => 35.0,
            'structure_class' => 'RESIDENTIAL',
        ]);
    }
}
