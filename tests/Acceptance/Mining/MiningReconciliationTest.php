<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\ReconcileStockpileAndShipmentAction;
use Modules\Mining\Domain\Models\MiningReconciliationInvestigation;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

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
    }

    public function test_rekonsiliasi_tiga_titik_dalam_batas_toleransi_berstatus_balanced(): void
    {
        // 100,000 kg weighed in, 100,000 kg stockpile in, 99,800 kg stockpile out, 99,750 kg shipped
        // Variance = 50 kg out of 100,000 kg = 5 bps (well within 50 bps default tolerance)
        $action = app(ReconcileStockpileAndShipmentAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 99800,
            totalShippedKg: 99750,
            idempotencyKey: 'min:rec:run:001'
        );

        $this->assertSame('BALANCED', $res->run->status);
        $this->assertSame(5, $res->run->variance_bps);
        $this->assertNull($res->investigation);
    }

    public function test_rekonsiliasi_melebihi_toleransi_membuka_entitas_investigasi_t5(): void
    {
        // 100,000 kg weighed in, but only 98,000 kg shipped (variance 2,000 kg = 200 bps > 50 bps tolerance!)
        $action = app(ReconcileStockpileAndShipmentAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 98000,
            totalShippedKg: 98000,
            idempotencyKey: 'min:rec:run:002'
        );

        $this->assertSame('DISCREPANCY_INVESTIGATION', $res->run->status);
        $this->assertSame(200, $res->run->variance_bps);
        $this->assertNotNull($res->investigation);
        $this->assertSame('OPEN', $res->investigation->status);

        $this->assertDatabaseHas('min_reconciliation_investigations', [
            'id' => $res->investigation->id,
            'reconciliation_run_id' => $res->run->id,
            'status' => 'OPEN',
        ]);
    }

    public function test_pembukaan_investigasi_dua_kali_dengan_key_sama_bersifat_idempoten(): void
    {
        $action = app(ReconcileStockpileAndShipmentAction::class);

        $res1 = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 98000,
            totalShippedKg: 98000,
            idempotencyKey: 'min:rec:run:003'
        );

        $res2 = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 98000,
            totalShippedKg: 98000,
            idempotencyKey: 'min:rec:run:003'
        );

        $this->assertSame($res1->run->id, $res2->run->id);
        $this->assertSame($res1->investigation?->id, $res2->investigation?->id);
        $this->assertSame(1, MiningReconciliationInvestigation::where('reconciliation_run_id', $res1->run->id)->count());
    }

    public function test_penyelesaian_investigasi_dengan_catatan_resolusi(): void
    {
        $action = app(ReconcileStockpileAndShipmentAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 98000,
            totalShippedKg: 98000,
            idempotencyKey: 'min:rec:run:004'
        );

        $investigation = $res->investigation;

        $action->resolveInvestigation(
            investigationId: $investigation->id,
            resolvedByUserId: 42,
            resolutionNotes: 'Discrepancy explained by rain moisture evaporation loss during conveyor transfer.'
        );

        $resolved = MiningReconciliationInvestigation::findOrFail($investigation->id);
        $this->assertSame('RESOLVED', $resolved->status);
        $this->assertStringContainsString('evaporation loss', $resolved->resolution_notes);
    }

    public function test_perhitungan_variansi_dalam_basis_poin_integer(): void
    {
        $action = app(ReconcileStockpileAndShipmentAction::class);

        // 500,000 kg vs 498,500 kg -> diff 1,500 kg
        // bps = (1,500 * 10,000) / 500,000 = 30 bps
        $res = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 500000,
            totalStockpileInKg: 500000,
            totalStockpileOutKg: 498500,
            totalShippedKg: 498500,
            idempotencyKey: 'min:rec:run:005'
        );

        $this->assertSame(30, $res->run->variance_bps);
        $this->assertSame('BALANCED', $res->run->status);
    }

    public function test_audit_trail_mencatat_eksekusi_rekonsiliasi(): void
    {
        $action = app(ReconcileStockpileAndShipmentAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            periodStart: '2026-10-01 00:00:00',
            periodEnd: '2026-10-31 23:59:59',
            totalWeighedKg: 100000,
            totalStockpileInKg: 100000,
            totalStockpileOutKg: 100000,
            totalShippedKg: 100000,
            idempotencyKey: 'min:rec:run:006'
        );

        $this->assertDatabaseHas('min_reconciliation_runs', [
            'id' => $res->run->id,
            'variance_bps' => 0,
            'status' => 'BALANCED',
        ]);
    }
}
