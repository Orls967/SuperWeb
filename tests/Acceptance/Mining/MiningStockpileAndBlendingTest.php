<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CreateBlendPlanAction;
use Modules\Mining\Application\Actions\ExecuteShipmentBlendAction;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\StockpileMovement;
use Modules\Shared\Domain\Algorithms\LinearProgramming\LpCertificateVerifier;
use Tests\TestCase;

class MiningStockpileAndBlendingTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningStockpile $stkHighGrade;

    protected MiningStockpile $stkLowGrade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        // Stockpile 1: High grade (2.10% Ni = 210 bps, 30,000 kg)
        // Stored as sums (PR4): total_kg = 30000, sum_kg_metal_bps = 30000 * 210 = 6,300,000
        $this->stkHighGrade = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_code' => 'STK-HG-01',
            'name' => 'High Grade Stockpile',
            'total_kg' => 30000,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 6300000,
        ]);

        // Stockpile 2: Low grade (1.50% Ni = 150 bps, 30,000 kg)
        // Stored as sums (PR4): total_kg = 30000, sum_kg_metal_bps = 30000 * 150 = 4,500,000
        $this->stkLowGrade = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_code' => 'STK-LG-01',
            'name' => 'Low Grade Stockpile',
            'total_kg' => 30000,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 4500000,
        ]);
    }

    public function test_stockpile_menyimpan_jumlah_bukan_rata_rata_pr4(): void
    {
        $stk = MiningStockpile::findOrFail($this->stkHighGrade->id);
        $this->assertSame(30000, $stk->total_kg);
        $this->assertSame(6300000, $stk->sum_kg_metal_bps);

        // Computed average grade on read via BigRational
        $this->assertSame('210', (string) $stk->average_metal_bps);
    }

    public function test_rata_rata_kadar_dihitung_dinamis_saat_dibaca_dengan_bigrational(): void
    {
        // Add 10,000 kg at 1.80% Ni (180 bps)
        $this->stkHighGrade->addMaterial(10000, ['metal_bps' => 180]);

        $stk = MiningStockpile::findOrFail($this->stkHighGrade->id);
        $this->assertSame(40000, $stk->total_kg);
        // Total sum = 6,300,000 + 1,800,000 = 8,100,000
        $this->assertSame(8100000, $stk->sum_kg_metal_bps);
        // 8,100,000 / 40,000 = 202.5 bps
        $this->assertSame(202, (int) $stk->average_metal_bps);
    }

    public function test_rencana_blending_menggunakan_rationalsimplex_dan_integer_round_sesuai_spesifikasi_t4(): void
    {
        // Target: Ship 40,000 kg with average grade >= 1.80% (180 bps)
        // High grade is 210 bps, Low grade is 150 bps.
        // Blending 20,000 kg HG + 20,000 kg LG: (20000*210 + 20000*150) / 40000 = 180 bps exactly!
        $action = app(CreateBlendPlanAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV PACIFIC CARRIER',
            targetMinKg: 40000,
            targetMaxKg: 40000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:001'
        );

        $this->assertTrue($res->isFeasible);
        $this->assertSame(40000, $res->totalAllocatedKg);
        $this->assertSame(20000, $res->allocations[$this->stkHighGrade->id]);
        $this->assertSame(20000, $res->allocations[$this->stkLowGrade->id]);
    }

    public function test_vektor_lp_20000_dan_20000_memenuhi_sertifikat_dual_lp_pr2(): void
    {
        $action = app(CreateBlendPlanAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV PACIFIC CARRIER',
            targetMinKg: 40000,
            targetMaxKg: 40000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:002'
        );

        $this->assertNotNull($res->plan->certificate_json);
        $certificate = json_decode($res->plan->certificate_json, true);

        $verifier = app(LpCertificateVerifier::class);
        $this->assertTrue($verifier->verifyOptimal(
            $res->lpProblem,
            $res->rationalSolution,
            $certificate
        ));
    }

    public function test_infeasible_blending_menghasilkan_sertifikat_farkas(): void
    {
        // Impossible request: Target grade 230 bps, but available stockpiles are only 210 and 150 bps!
        $action = app(CreateBlendPlanAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV IMPOSSIBLE',
            targetMinKg: 20000,
            targetMaxKg: 20000,
            targetSpecs: ['min_metal_bps' => 230],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:003'
        );

        $this->assertFalse($res->isFeasible);
        $this->assertNotNull($res->farkasRayCertificate);

        $verifier = app(LpCertificateVerifier::class);
        $this->assertTrue($verifier->verifyInfeasible(
            $res->lpProblem,
            $res->farkasRayCertificate
        ));
    }

    public function test_hasil_integer_blend_rounder_memenuhi_semua_batasan(): void
    {
        // Odd target: 40,001 kg with grade >= 180 bps
        $action = app(CreateBlendPlanAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV ODD CARRIER',
            targetMinKg: 40001,
            targetMaxKg: 40001,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:004'
        );

        $this->assertTrue($res->isFeasible);
        $this->assertSame(40001, $res->totalAllocatedKg);

        $hgAlloc = $res->allocations[$this->stkHighGrade->id];
        $lgAlloc = $res->allocations[$this->stkLowGrade->id];

        $this->assertSame(40001, $hgAlloc + $lgAlloc);
        // Average grade must be >= 180 bps: ($hgAlloc * 210 + $lgAlloc * 150) >= 40001 * 180
        $this->assertGreaterThanOrEqual(40001 * 180, ($hgAlloc * 210) + ($lgAlloc * 150));
    }

    public function test_pengapalan_menarik_movement_keluar_stockpile_sesuai_kuota_rencana(): void
    {
        $createPlanAction = app(CreateBlendPlanAction::class);
        $planRes = $createPlanAction->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV VOYAGE 01',
            targetMinKg: 30000,
            targetMaxKg: 30000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:005'
        );

        $executeAction = app(ExecuteShipmentBlendAction::class);
        $shipmentRes = $executeAction->handle(
            blendPlanId: $planRes->plan->id,
            idempotencyKey: 'min:shp:exec:001'
        );

        $this->assertSame('EXECUTED', $shipmentRes->plan->status);
        $this->assertSame(30000, $shipmentRes->totalShippedKg);

        // Verify stockpile movements recorded
        $this->assertDatabaseHas('min_stockpile_movements', [
            'stockpile_id' => $this->stkHighGrade->id,
            'movement_type' => 'OUTFLOW_BLEND',
            'direction' => 'OUT',
            'weight_kg' => 15000,
        ]);
        $this->assertDatabaseHas('min_stockpile_movements', [
            'stockpile_id' => $this->stkLowGrade->id,
            'movement_type' => 'OUTFLOW_BLEND',
            'direction' => 'OUT',
            'weight_kg' => 15000,
        ]);
    }

    public function test_pengapalan_melebihi_stok_tersedia_ditolak(): void
    {
        $createPlanAction = app(CreateBlendPlanAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/stok.*tidak cukup|exceeds/i');

        // Total available is 60,000 kg. Demanding 80,000 kg must be rejected!
        $createPlanAction->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV OVERSIZE',
            targetMinKg: 80000,
            targetMaxKg: 80000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:006'
        );
    }

    public function test_mutasi_keluar_mengurangi_akumulasi_stockpile_secara_atomik(): void
    {
        $createPlanAction = app(CreateBlendPlanAction::class);
        $planRes = $createPlanAction->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV REDUCTION',
            targetMinKg: 20000,
            targetMaxKg: 20000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:007'
        );

        $executeAction = app(ExecuteShipmentBlendAction::class);
        $executeAction->handle(
            blendPlanId: $planRes->plan->id,
            idempotencyKey: 'min:shp:exec:002'
        );

        // Before: 30,000 kg each. Each reduced by 10,000 kg.
        $hg = MiningStockpile::findOrFail($this->stkHighGrade->id);
        $lg = MiningStockpile::findOrFail($this->stkLowGrade->id);

        $this->assertSame(20000, $hg->total_kg);
        $this->assertSame(20000, $lg->total_kg);
        $this->assertSame(20000 * 210, $hg->sum_kg_metal_bps);
        $this->assertSame(20000 * 150, $lg->sum_kg_metal_bps);
    }

    public function test_model_stockpile_movement_menolak_update_fakta_append_only(): void
    {
        $movement = StockpileMovement::create([
            'id' => (string) Str::uuid(),
            'movement_uuid' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_id' => $this->stkHighGrade->id,
            'movement_type' => 'INFLOW_HAUL',
            'direction' => 'IN',
            'weight_kg' => 10000,
            'reference_type' => 'WEIGHBRIDGE',
            'reference_id' => (string) Str::uuid(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only|immutable/i');

        $movement->weight_kg = 5000;
        $movement->save();
    }

    public function test_model_stockpile_movement_menolak_delete_fakta_append_only(): void
    {
        $movement = StockpileMovement::create([
            'id' => (string) Str::uuid(),
            'movement_uuid' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_id' => $this->stkHighGrade->id,
            'movement_type' => 'INFLOW_HAUL',
            'direction' => 'IN',
            'weight_kg' => 10000,
            'reference_type' => 'WEIGHBRIDGE',
            'reference_id' => (string) Str::uuid(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only|immutable/i');

        $movement->delete();
    }

    public function test_benchmark_blending_simplex_rasional_di_bawah_0_2_detik_pr6(): void
    {
        $action = app(CreateBlendPlanAction::class);

        $start = microtime(true);
        $res = $action->handle(
            siteId: $this->site->id,
            targetVesselName: 'MV SPEED',
            targetMinKg: 20000,
            targetMaxKg: 20000,
            targetSpecs: ['min_metal_bps' => 180],
            availableStockpileIds: [$this->stkHighGrade->id, $this->stkLowGrade->id],
            idempotencyKey: 'min:bld:plan:008'
        );
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.20, $elapsed);
        $this->assertTrue($res->isFeasible);
    }
}
