<?php

declare(strict_types=1);

namespace Modules\Asset\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Asset\Application\Actions\DisposeFixedAssetWithGainLossAction;
use Modules\Asset\Application\Actions\RecognizeAssetImpairmentLossAction;
use Modules\Asset\Application\Actions\RecordAssetMaintenanceWorkOrderAction;
use Modules\Asset\Application\Actions\RegisterFixedAssetAction;
use Modules\Asset\Application\Actions\RevalueAssetCarryingAmountAction;
use Modules\Asset\Application\Actions\RunMonthlyDepreciationBatchAction;
use Modules\Asset\Application\Actions\TransferAssetCustodyLocationAction;
use Modules\Asset\Application\Actions\VerifyAssetHashChainAction;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetLocation;
use Tests\TestCase;

class AssetComprehensiveOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleAsset(int $cost = 120000000, int $salvage = 12000000): Asset
    {
        $category = AssetCategory::firstOrCreate(
            ['code' => 'MACHINERY'],
            ['name' => 'Heavy Machinery & Production Equipment', 'useful_life_months' => 60]
        );

        $location = AssetLocation::firstOrCreate(
            ['code' => 'WH-JKT-01'],
            ['name' => 'Jakarta Central Logistics Hub', 'level' => 'site']
        );

        $action = app(RegisterFixedAssetAction::class);

        return $action->execute([
            'name' => 'CNC Milling Lathe Multi-Axis',
            'acquisition_cost_idr' => $cost,
            'landed_cost_idr' => 0,
            'salvage_value_idr' => $salvage,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'brand' => 'DMG MORI',
            'serial_number' => 'DMG-2026-X99',
            'registered_by' => 'Asset Ops Lead',
        ]);
    }

    public function test_1_register_fixed_asset_with_genesis_event(): void
    {
        $asset = $this->createSampleAsset(100000000, 10000000);

        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertSame(100000000, $asset->acquisition_cost_idr);
        $this->assertSame(100000000, $asset->book_value_idr);
        $this->assertSame(AssetStatus::InUse, $asset->status);

        $this->assertDatabaseHas('ast_events', [
            'asset_id' => $asset->id,
            'sequence' => 1,
            'event_type' => 'acquisition',
            'prev_hash' => RegisterFixedAssetAction::GENESIS_HASH,
        ]);
    }

    public function test_2_run_monthly_straight_line_depreciation(): void
    {
        // 120M cost, 12M salvage, useful life 60 months => (120M - 12M) / 60 = 1.8M / month
        $asset = $this->createSampleAsset(120000000, 12000000);
        $action = app(RunMonthlyDepreciationBatchAction::class);

        $depreciation = $action->execute([
            'asset_id' => $asset->id,
            'period' => '2026-10',
            'useful_life_months' => 60,
        ]);

        $this->assertSame(1800000, $depreciation->amount_idr);
        $this->assertSame(1800000, $depreciation->accumulated_after_idr);

        $fresh = $asset->fresh();
        $this->assertSame(1800000, $fresh->accumulated_depreciation_idr);
        $this->assertSame(118200000, $fresh->book_value_idr);

        $this->assertDatabaseHas('ast_events', [
            'asset_id' => $asset->id,
            'sequence' => 2,
            'event_type' => 'depreciation',
        ]);
    }

    public function test_3_depreciation_capped_at_salvage_value(): void
    {
        $asset = $this->createSampleAsset(10000000, 8000000); // 10M cost, 8M salvage => depreciable base 2M
        $action = app(RunMonthlyDepreciationBatchAction::class);

        // Attempting to depreciate 5M when only 2M is available above salvage
        $depreciation = $action->execute([
            'asset_id' => $asset->id,
            'period' => '2026-10',
            'amount_idr' => 5000000,
        ]);

        $this->assertSame(2000000, $depreciation->amount_idr);
        $this->assertSame(8000000, $asset->fresh()->book_value_idr);
    }

    public function test_4_record_asset_maintenance_work_order(): void
    {
        $asset = $this->createSampleAsset();
        $action = app(RecordAssetMaintenanceWorkOrderAction::class);

        $workOrder = $action->execute([
            'asset_id' => $asset->id,
            'type' => 'preventive',
            'parts_cost_idr' => 2500000,
            'labor_cost_idr' => 1500000,
            'description' => 'Replace spindle bearings and calibrate optical sensors',
            'cost_treatment' => 'expense',
            'vendor' => 'PT Presisi Presisi Teknik',
        ]);

        $this->assertSame(4000000, $workOrder->total_cost_idr);
        $this->assertSame('completed', $workOrder->status);
        $this->assertSame('expense', $workOrder->cost_treatment);

        $this->assertDatabaseHas('ast_events', [
            'asset_id' => $asset->id,
            'sequence' => 2,
            'event_type' => 'maintenance',
        ]);
    }

    public function test_5_record_capitalized_maintenance_increases_landed_cost(): void
    {
        $asset = $this->createSampleAsset(100000000, 10000000);
        $action = app(RecordAssetMaintenanceWorkOrderAction::class);

        $action->execute([
            'asset_id' => $asset->id,
            'type' => 'overhaul',
            'parts_cost_idr' => 15000000,
            'labor_cost_idr' => 5000000,
            'description' => 'Major engine and transmission upgrade overhaul',
            'cost_treatment' => 'capitalized',
        ]);

        $fresh = $asset->fresh();
        $this->assertSame(20000000, $fresh->landed_cost_idr);
        $this->assertSame(120000000, $fresh->book_value_idr);
    }

    public function test_6_revalue_asset_carrying_amount_upward(): void
    {
        $asset = $this->createSampleAsset(100000000, 10000000);
        $action = app(RevalueAssetCarryingAmountAction::class);

        $reval = $action->execute([
            'asset_id' => $asset->id,
            'new_value_idr' => 135000000,
            'reason' => 'Surging industrial market valuation for CNC units',
            'appraiser_name' => 'KJPP Wahyu & Rekan',
        ]);

        $this->assertSame('upward', $reval->kind);
        $this->assertSame(35000000, $reval->difference_idr);
        $this->assertSame(135000000, $asset->fresh()->book_value_idr);
    }

    public function test_7_recognize_asset_impairment_loss(): void
    {
        $asset = $this->createSampleAsset(100000000, 10000000);
        $action = app(RecognizeAssetImpairmentLossAction::class);

        $reval = $action->execute([
            'asset_id' => $asset->id,
            'recoverable_amount_idr' => 60000000,
            'reason' => 'Technological obsolescence due to laser cutting disruption',
        ]);

        $this->assertSame('impairment', $reval->kind);
        $this->assertSame(-40000000, $reval->difference_idr);
        $this->assertSame(60000000, $asset->fresh()->book_value_idr);
    }

    public function test_8_dispose_fixed_asset_with_gain_or_loss(): void
    {
        $asset = $this->createSampleAsset(100000000, 10000000);
        $action = app(DisposeFixedAssetWithGainLossAction::class);

        // Book value 100M, sold for 115M => 15M gain
        $disposal = $action->execute([
            'asset_id' => $asset->id,
            'proceeds_idr' => 115000000,
            'method' => 'sale',
            'reason' => 'Sale to secondary contractor',
        ]);

        $this->assertSame(15000000, $disposal->gain_loss_idr);
        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
        $this->assertSame(0, $asset->fresh()->book_value_idr);
    }

    public function test_9_transfer_asset_custody_and_location(): void
    {
        $asset = $this->createSampleAsset();
        $targetLocation = AssetLocation::firstOrCreate(
            ['code' => 'WH-SBY-02'],
            ['name' => 'Surabaya Rungkut Industrial Yard', 'level' => 'site']
        );
        $user = User::factory()->create();

        $action = app(TransferAssetCustodyLocationAction::class);
        $result = $action->execute([
            'asset_id' => $asset->id,
            'new_location_id' => $targetLocation->id,
            'new_responsible_user_id' => $user->id,
            'reason' => 'Transferred for project phase II deployment',
        ]);

        $this->assertSame($targetLocation->id, $result['new_location_id']);
        $this->assertSame($user->id, $result['new_responsible_user_id']);
        $this->assertSame($targetLocation->id, $asset->fresh()->location_id);
    }

    public function test_10_verify_asset_hash_chain_integrity_and_detect_tampering(): void
    {
        $asset = $this->createSampleAsset();
        $verifier = app(VerifyAssetHashChainAction::class);

        // Chain with 1 event should be valid
        $check1 = $verifier->execute(['asset_id' => $asset->id]);
        $this->assertTrue($check1['is_valid']);
        $this->assertSame(1, $check1['total_events']);

        // Add 2 more events
        app(RunMonthlyDepreciationBatchAction::class)->execute([
            'asset_id' => $asset->id,
            'period' => '2026-10',
            'amount_idr' => 1000000,
        ]);
        app(RecordAssetMaintenanceWorkOrderAction::class)->execute([
            'asset_id' => $asset->id,
            'parts_cost_idr' => 500000,
            'labor_cost_idr' => 500000,
            'description' => 'Regular checkup',
        ]);

        $check2 = $verifier->execute(['asset_id' => $asset->id]);
        $this->assertTrue($check2['is_valid']);
        $this->assertSame(3, $check2['total_events']);

        // Tamper directly via raw DB query to simulate illicit alteration
        DB::table('ast_events')
            ->where('asset_id', $asset->id)
            ->where('sequence', 2)
            ->update(['payload' => json_encode(['action' => 'malicious_modification'])]);

        $check3 = $verifier->execute(['asset_id' => $asset->id]);
        $this->assertFalse($check3['is_valid']);
        $this->assertSame(2, $check3['tampered_sequence']);
    }
}
