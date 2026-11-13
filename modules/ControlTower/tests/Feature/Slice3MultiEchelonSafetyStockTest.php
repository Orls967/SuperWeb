<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\ControlTower\Application\Actions\DecideSafetyStockProposalAction;
use Modules\ControlTower\Domain\Algorithms\MultiEchelonSafetyStock;
use Modules\ControlTower\Domain\Certificates\SafetyStockPolicyCertificateVerifier;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;
use Modules\Procurement\Domain\Models\PoLine;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\ReceivingLine;
use Modules\Procurement\Domain\Models\ReceivingReport;
use Modules\Supplier\Contracts\Supplier;
use Tests\TestCase;

class Slice3MultiEchelonSafetyStockTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(string $code, string $name, int $leadTimeDays = 7, ?string $forcedUuid = null): Supplier
    {
        $id = $forcedUuid ?? (string) Str::uuid();
        $supplier = new Supplier;
        $supplier->forceFill([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'kind' => 'supplier',
            'status' => 'approved',
            'lead_time_days' => $leadTimeDays,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
        $supplier->save();

        return $supplier;
    }

    private function seedPoAndGrn(
        string $supplierId,
        string $poNumber,
        string $grnNumber,
        string $itemDescription,
        string $poCreatedAt,
        ?string $expectedDate,
        string $receivedAt,
        string $grnStatus = 'accepted',
    ): void {
        $po = PurchaseOrder::create([
            'number' => $poNumber,
            'supplier_id' => $supplierId,
            'title' => "PO {$poNumber}",
            'kind' => 'standard',
            'status' => 'received',
            'currency' => 'IDR',
            'total_amount' => 1_000_000,
            'expected_date' => $expectedDate,
        ]);

        DB::table('prc_purchase_orders')
            ->where('id', $po->id)
            ->update(['created_at' => $poCreatedAt.' 08:00:00']);

        $poLine = PoLine::create([
            'po_id' => $po->id,
            'description' => $itemDescription,
            'qty' => 100,
            'unit' => 'pcs',
            'unit_price' => 10_000,
            'line_total' => 1_000_000,
            'received_qty' => 100,
        ]);

        $grn = ReceivingReport::create([
            'number' => $grnNumber,
            'po_id' => $po->id,
            'status' => $grnStatus,
            'received_at' => $receivedAt,
        ]);

        ReceivingLine::create([
            'grn_id' => $grn->id,
            'po_line_id' => $poLine->id,
            'description' => $itemDescription,
            'ordered_qty' => 100,
            'received_qty' => 100,
            'accepted_qty' => $grnStatus === 'rejected' ? 0 : 100,
            'rejected_qty' => $grnStatus === 'rejected' ? 100 : 0,
        ]);
    }

    public function test_supplier_lead_time_statistics_returns_zeroes_when_no_samples(): void
    {
        /** @var SupplierLeadTimeStatistics $stats */
        $stats = app(SupplierLeadTimeStatistics::class);

        $this->assertSame([
            'mean_days' => 0,
            'sigma_days' => 0,
            'samples' => 0,
            'on_time_permil' => 0,
        ], $stats->forSupplierAndItem('00000000-0000-0000-0000-000000000000', 'SKU-NONE'));

        $this->assertNull($stats->primarySupplierForItem('SKU-NONE'));
        $this->assertSame([], $stats->allStatistics());
        $this->assertSame([], $stats->monthlyHistory('00000000-0000-0000-0000-000000000000', 6, '2026-11-15'));
    }

    public function test_supplier_lead_time_statistics_computes_real_prc_metrics_and_monthly_history(): void
    {
        $supA = $this->createSupplier('SUP-S3-A', 'PT Pemasok Alpha', 5, '11111111-1111-4111-8111-111111111111');
        $supB = $this->createSupplier('SUP-S3-B', 'PT Pemasok Beta', 5, '22222222-2222-4222-8222-222222222222');

        // 3 valid samples for supA + SKU-I3-01 across 3 months:
        // Sample 1 (accepted): created 2026-08-01, expected 2026-08-06, received 2026-08-05 -> 4 days, on-time
        $this->seedPoAndGrn((string) $supA->id, 'PO-S3-01', 'GRN-S3-01', 'SKU-I3-01 — Baut Baja', '2026-08-01', '2026-08-06', '2026-08-05', 'accepted');
        // Sample 2 (inspected): created 2026-09-01, expected 2026-09-06, received 2026-09-07 -> 6 days, late
        $this->seedPoAndGrn((string) $supA->id, 'PO-S3-02', 'GRN-S3-02', 'SKU-I3-01 — Baut Baja', '2026-09-01', '2026-09-06', '2026-09-07', 'inspected');
        // Sample 3 (open): created 2026-10-01, expected 2026-10-08, received 2026-10-09 -> 8 days, late
        $this->seedPoAndGrn((string) $supA->id, 'PO-S3-03', 'GRN-S3-03', 'SKU-I3-01', '2026-10-01', '2026-10-08', '2026-10-09', 'open');
        // Sample 4 (rejected -> MUST BE IGNORED)
        $this->seedPoAndGrn((string) $supA->id, 'PO-S3-04', 'GRN-S3-04', 'SKU-I3-01', '2026-10-10', '2026-10-12', '2026-10-25', 'rejected');
        // Sample 5 (quarantined -> MUST BE IGNORED)
        $this->seedPoAndGrn((string) $supA->id, 'PO-S3-05', 'GRN-S3-05', 'SKU-I3-01', '2026-10-12', '2026-10-14', '2026-10-28', 'quarantined');

        // 1 sample for supB + SKU-I3-02 (future month beyond cutoff test)
        $this->seedPoAndGrn((string) $supB->id, 'PO-S3-06', 'GRN-S3-06', 'SKU-I3-02: Mur', '2026-10-01', '2026-10-05', '2026-10-05', 'accepted');

        /** @var SupplierLeadTimeStatistics $stats */
        $stats = app(SupplierLeadTimeStatistics::class);

        // Samples for supA & SKU-I3-01: [4, 6, 8] -> mean=6, variance=(4+0+4)/3=2.6667, sigma=sqrt(2.6667)=1.633 -> rounded to 2
        // On-time: 1 of 3 -> 333 permil
        $res = $stats->forSupplierAndItem((string) $supA->id, 'SKU-I3-01');
        $this->assertSame([
            'mean_days' => 6,
            'sigma_days' => 2,
            'samples' => 3,
            'on_time_permil' => 333,
        ], $res);

        // allStatistics sorted by (supplier_ref, item_code)
        $all = $stats->allStatistics();
        $this->assertCount(2, $all);
        $this->assertSame((string) $supA->id, $all[0]['supplier_ref']);
        $this->assertSame('PT Pemasok Alpha', $all[0]['supplier_name']);
        $this->assertSame('SKU-I3-01', $all[0]['item_code']);
        $this->assertSame(6, $all[0]['mean_days']);
        $this->assertSame(2, $all[0]['sigma_days']);
        $this->assertSame(3, $all[0]['samples']);

        $this->assertSame((string) $supB->id, $all[1]['supplier_ref']);
        $this->assertSame('PT Pemasok Beta', $all[1]['supplier_name']);
        $this->assertSame('SKU-I3-02', $all[1]['item_code']);
        $this->assertSame(4, $all[1]['mean_days']);
        $this->assertSame(0, $all[1]['sigma_days']);
        $this->assertSame(1000, $all[1]['on_time_permil']);

        // monthlyHistory: max 2 months up to 2026-10-31, returned oldest to newest -> 2026-09 then 2026-10
        $history = $stats->monthlyHistory((string) $supA->id, 2, '2026-10-31');
        $this->assertCount(2, $history);
        $this->assertSame('2026-09', $history[0]['period']);
        $this->assertSame(6, $history[0]['mean_days']);
        $this->assertSame(0, $history[0]['on_time_permil']);
        $this->assertSame('2026-10', $history[1]['period']);
        $this->assertSame(8, $history[1]['mean_days']);
        $this->assertSame(1, $history[1]['samples']);
    }

    public function test_primary_supplier_tie_breaker_prefers_most_samples_then_smallest_supplier_ref(): void
    {
        $supSmaller = $this->createSupplier('SUP-TIE-1', 'Supplier Smaller UUID', 5, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $supLarger = $this->createSupplier('SUP-TIE-2', 'Supplier Larger UUID', 5, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

        $this->seedPoAndGrn((string) $supLarger->id, 'PO-TIE-1', 'GRN-TIE-1', 'SKU-TIE-01', '2026-10-01', '2026-10-05', '2026-10-05');
        $this->seedPoAndGrn((string) $supLarger->id, 'PO-TIE-2', 'GRN-TIE-2', 'SKU-TIE-01', '2026-10-02', '2026-10-06', '2026-10-06');
        $this->seedPoAndGrn((string) $supSmaller->id, 'PO-TIE-3', 'GRN-TIE-3', 'SKU-TIE-01', '2026-10-03', '2026-10-07', '2026-10-07');

        /** @var SupplierLeadTimeStatistics $stats */
        $stats = app(SupplierLeadTimeStatistics::class);

        // supLarger has 2 samples vs 1 -> supLarger wins
        $this->assertSame((string) $supLarger->id, $stats->primarySupplierForItem('SKU-TIE-01'));

        // Add 2nd sample for supSmaller -> tied 2 vs 2 -> smaller supplier_ref wins
        $this->seedPoAndGrn((string) $supSmaller->id, 'PO-TIE-4', 'GRN-TIE-4', 'SKU-TIE-01', '2026-10-04', '2026-10-08', '2026-10-08');
        $this->assertSame((string) $supSmaller->id, $stats->primarySupplierForItem('SKU-TIE-01'));
    }

    public function test_i3_multi_echelon_safety_stock_covers_all_9_abc_xyz_cells_and_unified_formula(): void
    {
        /** @var MultiEchelonSafetyStock $algo */
        $algo = app(MultiEchelonSafetyStock::class);
        $verifier = new SafetyStockPolicyCertificateVerifier;

        // ABC catalog classification: <= 8000 A, <= 9500 B, > 9500 C
        $abcMap = $algo->classifyAbcCatalog([
            ['item_code' => 'SKU-1', 'annual_usage_value_idr' => 500_000],
            ['item_code' => 'SKU-2', 'annual_usage_value_idr' => 300_000], // cum 8000 bps -> A
            ['item_code' => 'SKU-3', 'annual_usage_value_idr' => 150_000], // cum 9500 bps -> B
            ['item_code' => 'SKU-4', 'annual_usage_value_idr' => 50_000],  // cum 10000 bps -> C
        ]);
        $this->assertSame('A', $abcMap['SKU-1']);
        $this->assertSame('A', $abcMap['SKU-2']);
        $this->assertSame('B', $abcMap['SKU-3']);
        $this->assertSame('C', $abcMap['SKU-4']);

        // Demand series producing X (cv < 2500), Y (2500..6500), Z (> 6500)
        // X: [100, 100, 100] -> mean=100, demandVar=0, cv=0
        // Y: [60, 140] -> mean=100, stdDev=40, cv=4000
        // Z: [20, 180] -> mean=100, stdDev=80, cv=8000
        $cells = [
            ['A', [100, 100, 100], 'AX', 23_300, 466, 966],   // combinedStdDev = 100*2 = 200; ceil(2.33*200)=466; ROP=500+466=966
            ['A', [60, 140], 'AY', 20_500, 450, 950],         // leadTime*demandVar + mean^2*sigma^2 = 5*1600 + 10000*4 = 48000; sqrt=219.089; ceil(2.05*219.089)=450; ROP=950
            ['A', [20, 180], 'AZ', 19_600, 526, 1026],        // 5*6400 + 40000 = 72000; sqrt=268.328; ceil(1.96*268.328)=526; ROP=1026
            ['B', [100, 100, 100], 'BX', 16_500, 330, 830],   // ceil(1.65*200)=330
            ['B', [60, 140], 'BY', 16_500, 362, 862],         // ceil(1.65*219.089)=362
            ['B', [20, 180], 'BZ', 12_800, 344, 844],         // ceil(1.28*268.328)=344
            ['C', [100, 100, 100], 'CX', 12_800, 256, 756],   // ceil(1.28*200)=256
            ['C', [60, 140], 'CY', 10_400, 228, 728],         // ceil(1.04*219.089)=228
            ['C', [20, 180], 'CZ', 8_400, 226, 726],          // ceil(0.84*268.328)=226
        ];

        foreach ($cells as [$abc, $demand, $expectedCell, $expectedZ, $expectedSs, $expectedRop]) {
            $res = $algo->calculate(
                itemCode: "SKU-{$expectedCell}",
                abcClass: $abc,
                demandHistory: $demand,
                leadTimeDays: 5,
                leadTimeSigmaDays: 2,
            );

            $this->assertSame($expectedCell, $res['abc_class'].$res['xyz_class']);
            $this->assertSame($expectedZ, $res['z_factor_bps']);
            $this->assertSame($expectedSs, $res['safety_stock_qty']);
            $this->assertSame($expectedRop, $res['reorder_point_qty']);
            $this->assertTrue($verifier->verify($res['certificate']));

            // Echelon breakdown PLANT + DC + OUTLET sums exactly to total
            $breakdown = $res['echelon_breakdown'];
            $this->assertSame(['PLANT', 'DC', 'OUTLET'], array_keys($breakdown));
            $this->assertSame(
                $expectedSs,
                $breakdown['PLANT']['safety_stock_qty'] + $breakdown['DC']['safety_stock_qty'] + $breakdown['OUTLET']['safety_stock_qty'],
            );
            $this->assertSame(
                $expectedRop,
                $breakdown['PLANT']['reorder_point_qty'] + $breakdown['DC']['reorder_point_qty'] + $breakdown['OUTLET']['reorder_point_qty'],
            );
        }
    }

    public function test_propose_and_four_eyes_approve_updates_safety_stock_policy_and_echelon_stocks(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $logisticsAdmin = User::factory()->create(['role' => 'logistics_admin']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        $sup = $this->createSupplier('SUP-S3-PROP', 'PT Pemasok Proposal', 5);
        // Real PO+GRN: lead times 4 & 6 -> mean_days = 5, sigma_days = 1
        $this->seedPoAndGrn((string) $sup->id, 'PO-P-1', 'GRN-P-1', 'SKU-PROP-01', '2026-10-01', '2026-10-06', '2026-10-05');
        $this->seedPoAndGrn((string) $sup->id, 'PO-P-2', 'GRN-P-2', 'SKU-PROP-01', '2026-10-10', '2026-10-16', '2026-10-16');

        foreach (['PLANT', 'DC', 'OUTLET'] as $node) {
            EchelonStock::create([
                'item_code' => 'SKU-PROP-01',
                'item_name' => 'Kompresor Industri',
                'echelon_node' => $node,
                'on_hand_qty' => 200,
                'in_transit_qty' => 0,
                'reserved_qty' => 20,
                'safety_stock_qty' => 10,
                'abc_class' => 'A',
                'xyz_class' => 'X',
            ]);
        }

        // Propose via HTTP endpoint
        $proposeResp = $this->actingAs($planner)->postJson('/control-tower/safety-stock/propose', [
            'item_code' => 'SKU-PROP-01',
            'abc_class' => 'A',
            'demand_history' => [100, 100, 100],
        ]);
        $proposeResp->assertStatus(201);

        /** @var SafetyStockPolicy $policy */
        $policy = SafetyStockPolicy::query()->where('item_code', 'SKU-PROP-01')->firstOrFail();
        $this->assertSame('pending_approval', $policy->status);
        $this->assertSame(0, $policy->safety_stock_qty);
        $this->assertSame(233, $policy->proposed_safety_stock_qty); // Z=2.33 * (100 * 1) = 233
        $this->assertSame(733, $policy->proposed_reorder_point_qty); // 100*5 + 233 = 733
        $this->assertSame(5, $policy->lead_time_mean_days);
        $this->assertSame(1, $policy->lead_time_sigma_days);
        $this->assertNotNull($policy->approval_id);

        // EchelonStock must NOT be modified before approval
        $this->assertSame(10, EchelonStock::query()->where('item_code', 'SKU-PROP-01')->where('echelon_node', 'DC')->value('safety_stock_qty'));

        // Four-eyes check: proposer cannot approve own proposal (even if role were logistics_admin)
        $selfProposerAdmin = User::factory()->create(['role' => 'logistics_admin']);
        $policy->update(['proposed_by_user_id' => $selfProposerAdmin->id]);
        // Also test via DecideSafetyStockProposalAction directly for four-eyes
        $this->actingAs($selfProposerAdmin)
            ->postJson("/control-tower/safety-stock/{$policy->id}/decide", ['decision' => 'approve'])
            ->assertStatus(422);

        // Restore original proposer for real approval test
        $policy->update(['proposed_by_user_id' => $planner->id]);

        // Role check: planner & auditor cannot decide (403)
        $this->actingAs($planner)
            ->postJson("/control-tower/safety-stock/{$policy->id}/decide", ['decision' => 'approve'])
            ->assertStatus(403);
        $this->actingAs($auditor)
            ->postJson("/control-tower/safety-stock/{$policy->id}/decide", ['decision' => 'approve'])
            ->assertStatus(403);

        // Different logistics_admin approves -> applied to SafetyStockPolicy & EchelonStock
        $approveResp = $this->actingAs($logisticsAdmin)->postJson("/control-tower/safety-stock/{$policy->id}/decide", [
            'decision' => 'approve',
            'notes' => 'Disetujui sesuai data historis GRN.',
        ]);
        $approveResp->assertOk();

        $policy->refresh();
        $this->assertSame('approved', $policy->status);
        $this->assertSame(233, $policy->safety_stock_qty);
        $this->assertSame(733, $policy->reorder_point_qty);
        $this->assertSame($logisticsAdmin->id, $policy->decided_by_user_id);

        $echelonSum = (int) EchelonStock::query()->where('item_code', 'SKU-PROP-01')->sum('safety_stock_qty');
        $this->assertSame(233, $echelonSum);
    }

    public function test_propose_and_reject_four_eyes_preserves_existing_active_policy_and_echelon(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $admin = User::factory()->create(['role' => 'admin']);

        SafetyStockPolicy::create([
            'item_code' => 'SKU-REJ-01',
            'abc_class' => 'B',
            'xyz_class' => 'X',
            'service_level_bps' => 9500,
            'safety_stock_qty' => 50,
            'reorder_point_qty' => 200,
            'status' => 'approved',
        ]);

        EchelonStock::create([
            'item_code' => 'SKU-REJ-01',
            'item_name' => 'Filter Oli',
            'echelon_node' => 'DC',
            'on_hand_qty' => 150,
            'in_transit_qty' => 0,
            'reserved_qty' => 10,
            'safety_stock_qty' => 25,
            'abc_class' => 'B',
            'xyz_class' => 'X',
        ]);

        $this->actingAs($planner)->postJson('/control-tower/safety-stock/propose', [
            'item_code' => 'SKU-REJ-01',
            'abc_class' => 'A',
            'demand_history' => [100, 140, 60],
            'fallback_lead_time_days' => 10,
        ])->assertStatus(201);

        /** @var SafetyStockPolicy $policy */
        $policy = SafetyStockPolicy::query()->where('item_code', 'SKU-REJ-01')->firstOrFail();
        $this->assertSame('pending_approval', $policy->status);
        $this->assertSame(50, $policy->safety_stock_qty);

        // Reject without reason fails (422)
        $this->actingAs($admin)->postJson("/control-tower/safety-stock/{$policy->id}/decide", [
            'decision' => 'reject',
            'reason' => '',
        ])->assertStatus(422);

        // Explicit four-eyes rejection via Action/Controller
        $this->actingAs($admin)->postJson("/control-tower/safety-stock/{$policy->id}/decide", [
            'decision' => 'reject',
            'reason' => 'Lonjakan permintaan bersifat musiman sementara.',
        ])->assertOk();

        $policy->refresh();
        $this->assertSame('rejected', $policy->status);
        $this->assertSame(50, $policy->safety_stock_qty);
        $this->assertSame(200, $policy->reorder_point_qty);
        $this->assertSame(25, EchelonStock::query()->where('item_code', 'SKU-REJ-01')->where('echelon_node', 'DC')->value('safety_stock_qty'));

        // Verify direct action four-eyes exception on reject too
        $this->actingAs($planner)->postJson('/control-tower/safety-stock/propose', [
            'item_code' => 'SKU-REJ-01',
            'abc_class' => 'B',
            'demand_history' => [100, 100, 100],
        ])->assertStatus(201);

        $action = app(DecideSafetyStockProposalAction::class);
        $this->expectException(\DomainException::class);
        $action->reject($policy->fresh(), $planner, 'Self reject');
    }

    public function test_tower_safety_stock_command_and_csv_export(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $operator = User::factory()->create(['role' => 'operator']);

        $sup = $this->createSupplier('SUP-CMD', 'PT Pemasok Command', 4);
        $this->seedPoAndGrn((string) $sup->id, 'PO-CMD-1', 'GRN-CMD-1', 'SKU-CMD-01', '2026-10-01', '2026-10-05', '2026-10-05');
        $this->seedPoAndGrn((string) $sup->id, 'PO-CMD-2', 'GRN-CMD-2', 'SKU-CMD-01', '2026-10-05', '2026-10-11', '2026-10-11');

        EchelonStock::create([
            'item_code' => 'SKU-CMD-01',
            'item_name' => 'Katup Tekanan',
            'echelon_node' => 'DC',
            'on_hand_qty' => 300,
            'in_transit_qty' => 0,
            'reserved_qty' => 0,
            'safety_stock_qty' => 0,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);

        DemandForecast::create([
            'forecast_code' => 'FC-CMD-01',
            'item_code' => 'SKU-CMD-01',
            'period' => '2026-10',
            'algorithm_model' => 'MOVING_AVG',
            'forecast_qty' => 120,
            'actual_qty' => 120,
        ]);

        $this->artisan('tower:safety-stock', ['--item' => 'SKU-CMD-01', '--node' => 'DC'])
            ->expectsOutputToContain('SKU-CMD-01')
            ->expectsOutputToContain('Calculated multi-echelon safety stock for 1 item(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sct_safety_stock_policies', [
            'item_code' => 'SKU-CMD-01',
            'abc_class' => 'A',
            'xyz_class' => 'X',
            'lead_time_mean_days' => 5,
            'lead_time_sigma_days' => 1,
        ]);

        // Unauthorized role gets 403 on export
        $this->actingAs($operator)->get('/control-tower/safety-stock/export.csv')->assertStatus(403);

        // Auditor can download CSV
        $exportResp = $this->actingAs($auditor)->get('/control-tower/safety-stock/export.csv');
        $exportResp->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResp->headers->get('Content-Type'));
        $csvContent = $exportResp->streamedContent();
        $this->assertStringContainsString('item_code,abc_class,xyz_class', $csvContent);
        $this->assertStringContainsString('SKU-CMD-01,A,X', $csvContent);
    }
}
