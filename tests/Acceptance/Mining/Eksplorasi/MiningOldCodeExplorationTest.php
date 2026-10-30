<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining\Eksplorasi;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CalculatePnbpRoyaltyFromVerifiedTonnageAction;
use Modules\Mining\Application\Actions\CoordinateIntegratedResourceCommandCenterSlaAction;
use Modules\Mining\Application\Actions\DispatchHaulTruckAndDetectFuelAnomalyAction;
use Modules\Mining\Application\Actions\DispatchMiningSolarHybridPowerAndCarbonOffsetAction;
use Modules\Mining\Application\Actions\EnforceBlastRadiusSafetyAndGeotechLockAction;
use Modules\Mining\Application\Actions\EvaluateHseFatigueMentalHealthAndContractorPermitAction;
use Modules\Mining\Application\Actions\ExecuteSmelterMassBalanceRecoveryAction;
use Modules\Mining\Application\Actions\PlanMineBlockExtractionAndStrippingRatioAction;
use Modules\Mining\Application\Actions\PostReclamationGuaranteeBondAndAmortizeAction;
use Modules\Mining\Application\Actions\ReconcileOreGradeMineToPortVarianceAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeHashChainTicketAction;
use Modules\Mining\Application\Actions\ScheduleBargeTransshipmentAndExportDemurrageAction;
use Modules\Mining\Application\Actions\SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction;
use Modules\Mining\Application\Actions\SimulateUndergroundDigitalTwinVentilationAndGeotechAction;
use Modules\Mining\Application\Actions\VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Ledger\MiningAccounts;
use Tests\TestCase;

class MiningOldCodeExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_eksplorasi_15_action_lama_mining(): void
    {
        $site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-KOLAKA-01',
            'name' => 'Kolaka Nickel Mine Site',
            'commodity' => 'NICKEL',
            'location' => 'Pomalaa, Kolaka, Sulawesi Tenggara',
            'status' => 'active',
        ]);

        $pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'pit_code' => 'PIT-ALPHA-01',
            'name' => 'Pit Alpha Central',
            'target_production_ton' => 50000,
            'status' => 'active',
        ]);

        $equipment = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'equipment_code' => 'DT-042',
            'type' => 'haul_truck',
            'model' => 'Komatsu HD785-7',
            'capacity_ton' => 50.0,
            'status' => 'idle',
            'engine_hours' => 1250,
        ]);

        // 1. RecordWeighbridgeHashChainTicketAction: SALAH
        // Evaluasi: Tonase bertipe float, tidak ada validasi toleransi tara truk (tare_tolerance_bps),
        // tidak ada penandaan overload berbasis kapasitas terdaftar, tidak ada penolakan duplikat siklus truk < 600 detik,
        // penomoran tiket menggunakan random_bytes(4) bukan sekuensial gapless,
        // hash chain ad-hoc format bebas tanpa verifier kanonikal CanonicalHashChain,
        // tidak mencatat StockpileMovement atomik, dan tidak memverifikasi izin operator atau status area.
        $wbAction = app(RecordWeighbridgeHashChainTicketAction::class);
        $resWb = $wbAction->handle(
            siteId: (string) $site->id,
            truckPlateNumber: 'DT-042',
            grossWeightTon: 85.5,
            tareWeightTon: 35.0,
            nickelGradePercentage: 1.82,
            destinationStockpile: 'STK-RUN-OF-MINE-01'
        );
        $this->assertNotNull($resWb['ticket']);
        $this->assertSame(50.5, $resWb['net_weight_ton']);
        $this->assertStringStartsWith('WB-', $resWb['ticket_number']);

        // 2. CalculatePnbpRoyaltyFromVerifiedTonnageAction: SALAH
        // Evaluasi: Menerima angka tonase bebas masukan pemanggil alih-alih menghitung dari tiket timbang terverifikasi di DB (jalan pintas terlarang §12F),
        // perhitungan float tanpa formula integer bps eksak T-6, tidak ada pemisahan provisional vs final delta,
        // tidak ada zona waktu lokasi (WITA), dan tidak ada posting jurnal ke buku besar.
        $royaltyAction = app(CalculatePnbpRoyaltyFromVerifiedTonnageAction::class);
        $resRoyalty = $royaltyAction->handle(
            siteId: (string) $site->id,
            period: '2026-10',
            totalProductionTon: 50000.0,
            commodityBenchmarkPriceIdr: 450000,
            royaltyRatePercentage: 10.0
        );
        $this->assertNotNull($resRoyalty['calculation']);
        $this->assertSame(2250000000, $resRoyalty['royalty_due_idr']);

        // 3. DispatchHaulTruckAndDetectFuelAnomalyAction: SALAH
        // Evaluasi: Tidak menggunakan HungarianSolver (B0.5) untuk alokasi optimal armada-shovel,
        // hanya memproses 1 truk individual pasca-trip, tidak memeriksa status grounded atau area dihentikan,
        // tidak ada override dispatcher beserta alasan, baseline konsumsi BBM konstanta statis (1.85 L/km)
        // alih-alih RobustStatistics median/MAD (B0.3, T-8), dan tonase bertipe float.
        $dispatchAction = app(DispatchHaulTruckAndDetectFuelAnomalyAction::class);
        $resDispatch = $dispatchAction->handle(
            siteId: (string) $site->id,
            pitId: (string) $pit->id,
            equipmentId: (string) $equipment->id,
            shiftCode: 'SHIFT-DAY-01',
            targetPayloadTon: 50.0,
            actualPayloadTon: 50.5,
            fuelConsumedLiter: 85.0,
            distanceKm: 45.0
        );
        $this->assertNotNull($resDispatch['dispatch_run']);
        $this->assertFalse($resDispatch['fuel_anomaly_detected']);

        // 4. ReconcileOreGradeMineToPortVarianceAction: SALAH
        // Evaluasi: Hanya membandingkan dua nilai float kadar nikel tanpa memverifikasi kekekalan massa timbang vs stockpile vs pengapalan,
        // tidak membuat entitas database ReconciliationRun atau Investigation saat selisih melebihi toleransi recon_tolerance_bps.
        $reconAction = app(ReconcileOreGradeMineToPortVarianceAction::class);
        $resRecon = $reconAction->handle(
            lotCode: 'LOT-KOL-2026-001',
            mineGradeNiPct: 1.85,
            portGradeNiPct: 1.72,
            lotTonnage: 10000.0
        );
        $this->assertTrue($resRecon['is_dispute_flagged']);

        // 5. PlanMineBlockExtractionAndStrippingRatioAction: SEBAGIAN
        // Evaluasi: Menghitung stripping ratio dan biaya estimasi, namun perhitungan memakai float dan belum terikat kalender penambangan deterministik.
        $planAction = app(PlanMineBlockExtractionAndStrippingRatioAction::class);
        $resPlan = $planAction->handle(
            siteId: (string) $site->id,
            pitId: (string) $pit->id,
            oreTonnage: 25000.0,
            wasteOverburdenBcm: 75000.0
        );
        $this->assertSame(3.0, $resPlan['stripping_ratio']);
        $this->assertTrue($resPlan['is_economically_viable']);

        // 6. EnforceBlastRadiusSafetyAndGeotechLockAction: SEBAGIAN
        // Evaluasi: Mengevaluasi radius keselamatan ledakan, namun belum terintegrasi ApprovalEngineInterface dan tidak menghentikan area tambang untuk operasional lain.
        $blastAction = app(EnforceBlastRadiusSafetyAndGeotechLockAction::class);
        $resBlast = $blastAction->handle(
            pitId: (string) $pit->id,
            blastCode: 'BLAST-2026-10-01',
            blastCoordLat: -4.052,
            blastCoordLng: 121.612,
            holesCount: 30,
            explosivesKg: 1500.0,
            activePersonnelInRadius: 0
        );
        $this->assertTrue($resBlast['is_clear_for_detonation']);

        // 7. PostReclamationGuaranteeBondAndAmortizeAction: SEBAGIAN
        $reclamationAction = app(PostReclamationGuaranteeBondAndAmortizeAction::class);
        $resRec = $reclamationAction->handle((string) $site->id, 'REC-01', 50.0, 1000000000, 0.70);
        $this->assertTrue($resRec['is_bond_release_eligible']);

        // 8. SimulateUndergroundDigitalTwinVentilationAndGeotechAction: KERANGKA
        $simAction = app(SimulateUndergroundDigitalTwinVentilationAndGeotechAction::class);
        $resSim = $simAction->handle((string) $pit->id, now()->toIso8601String(), -4.05, 121.60);
        $this->assertTrue($resSim['safety_cleared']);

        // 9. EvaluateHseFatigueMentalHealthAndContractorPermitAction: SEBAGIAN
        $permitAction = app(EvaluateHseFatigueMentalHealthAndContractorPermitAction::class);
        $resPermit = $permitAction->handle(
            siteId: (string) $site->id,
            permitType: 'HOT_WORK',
            workerPartyId: 'WRK-001',
            validFrom: now()->subHour()->toIso8601String(),
            validUntil: now()->addHours(4)->toIso8601String(),
            workerLat: -4.051,
            workerLng: 121.611,
            allowedLat: -4.051,
            allowedLng: 121.611
        );
        $this->assertNotNull($resPermit['permit']);

        // 10. DispatchMiningSolarHybridPowerAndCarbonOffsetAction: KERANGKA
        $solarAction = app(DispatchMiningSolarHybridPowerAndCarbonOffsetAction::class);
        $this->assertInstanceOf(DispatchMiningSolarHybridPowerAndCarbonOffsetAction::class, $solarAction);

        // 11. VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction: KERANGKA
        $waterAction = app(VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction::class);
        $this->assertInstanceOf(VerifyTailingsWaterQualityAndBiodiversityRehabilitationAction::class, $waterAction);

        // 12. SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction: SEBAGIAN
        $settleAction = app(SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction::class);
        $this->assertInstanceOf(SettleSmelterMetalsTradingHedgeAndAssayFinalizationAction::class, $settleAction);

        // 13. ExecuteSmelterMassBalanceRecoveryAction: SEBAGIAN
        $smelterAction = app(ExecuteSmelterMassBalanceRecoveryAction::class);
        $this->assertInstanceOf(ExecuteSmelterMassBalanceRecoveryAction::class, $smelterAction);

        // 14. ScheduleBargeTransshipmentAndExportDemurrageAction: SEBAGIAN
        $bargeAction = app(ScheduleBargeTransshipmentAndExportDemurrageAction::class);
        $this->assertInstanceOf(ScheduleBargeTransshipmentAndExportDemurrageAction::class, $bargeAction);

        // 15. CoordinateIntegratedResourceCommandCenterSlaAction: KERANGKA
        $cmdAction = app(CoordinateIntegratedResourceCommandCenterSlaAction::class);
        $this->assertInstanceOf(CoordinateIntegratedResourceCommandCenterSlaAction::class, $cmdAction);
    }

    public function test_eksplorasi_mining_accounts_dan_audit_command(): void
    {
        // 1. Cek MiningAccounts
        $this->assertTrue(class_exists(MiningAccounts::class));
        $definitions = MiningAccounts::definitions();
        $this->assertNotEmpty($definitions);

        // Identifikasi akun allow_negative => true tanpa alasan pembenaran
        $allowNegativeAccounts = array_filter($definitions, fn ($acc) => $acc['allow_negative'] === true);
        $this->assertNotEmpty($allowNegativeAccounts);

        // Buktikan bahwa expense:mining_royalty:IDR dan min:royalty_payable:IDR mengizinkan saldo negatif
        $negativeCodes = array_column($allowNegativeAccounts, 'code');
        $this->assertContains('expense:mining_royalty:IDR', $negativeCodes);
        $this->assertContains('min:royalty_payable:IDR', $negativeCodes);

        // 2. Cek min:audit command (46 baris)
        // Command lama hanya memeriksa dispatched equipment vs in_progress runs,
        // tidak memeriksa kesetaraan dua sumber tonase timbang vs stockpile vs pengapalan,
        // tidak memeriksa royalti vs produksi terverifikasi, dan tidak memverifikasi hash-chain atau sertifikat optimasi.
        $exitCode = Artisan::call('min:audit');
        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Mining Operations & Fleet Audit', $output);
        $this->assertStringContainsString('Mining audit PASSED cleanly', $output);
    }

    public function test_eksplorasi_rute_dan_halaman_mining_per_role(): void
    {
        $routes = Route::getRoutes();

        $miningRoutes = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'mining')) {
                $miningRoutes[$route->getName() ?? $uri] = [
                    'methods' => $route->methods(),
                    'uri' => $uri,
                    'middleware' => $route->middleware(),
                ];
            }
        }

        $this->assertNotEmpty($miningRoutes);
        $this->assertArrayHasKey('mining.index', $miningRoutes);
        $this->assertArrayHasKey('mining.operations.weighbridge', $miningRoutes);
        $this->assertArrayHasKey('mining.operations.haul-dispatch', $miningRoutes);

        // Buktikan bahwa seluruh rute saat ini terkunci di bawah 'role:admin,mining_admin,site_manager'
        // dan belum memfasilitasi role spesifik KONSEP §12F seperti weighbridge_operator atau dispatcher_mine.
        $middleware = $miningRoutes['mining.operations.weighbridge']['middleware'];
        $hasRestrictedRole = false;
        foreach ($middleware as $m) {
            if (str_contains($m, 'role:admin,mining_admin,site_manager')) {
                $hasRestrictedRole = true;
            }
        }
        $this->assertTrue($hasRestrictedRole);
    }
}
