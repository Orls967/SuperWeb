<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media\Eksplorasi;

use App\Quality\Audit\Fixtures\MedAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Med\Application\Actions\BookProgrammaticAdInventoryCampaignAction;
use Modules\Med\Application\Actions\DistributeBoxOfficeAndStreamingRoyaltyAction;
use Modules\Med\Application\Actions\GreenlightStudioProductionBudgetAction;
use Modules\Med\Application\Actions\LicenseContentRightsToTerritoryAction;
use Modules\Med\Application\Actions\SettleTalentResidualPayoutAction;
use Modules\Med\Application\Actions\VerifyDigitalAssetWatermarkChainAction;
use Modules\Med\Application\Services\DistributionAndAdvertisingService;
use Modules\Med\Application\Services\StudioAndContentProductionService;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedTalentContract;
use Tests\TestCase;

/**
 * L15.0: Eksplorasi & Pembuktian Kondisi Kode Lama Lini 15 (Media & Kreatif).
 */
class MediaExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_explores_greenlight_studio_production_budget_action(): void
    {
        $project = MedProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'PRJ-EXP-001',
            'title' => 'Exploration Movie',
            'genre' => 'SCI_FI',
            'phase' => 'BRIEF',
            'budget_limit_minor' => 0,
            'crew_cost_minor' => 0,
            'vendor_cost_minor' => 0,
            'studio_cost_minor' => 0,
            'total_production_cost_minor' => 0,
            'box_office_revenue_minor' => 0,
            'status' => 'IN_DEVELOPMENT',
        ]);

        $action = app(GreenlightStudioProductionBudgetAction::class);
        $updated = $action->execute((string) $project->id, 500_000_000);

        expect($updated->phase)->toBe('PRE_PROD')
            ->and($updated->budget_limit_minor)->toBe(500_000_000);
    }

    public function test_explores_license_content_rights_action_and_identifies_overlap_gap(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-EXP-001',
            'title' => 'Legacy IP Hit',
            'ip_type' => 'SHOW_FORMAT',
            'owner_entity_id' => 'ENT-STUDIO-01',
            'status' => 'ACTIVE',
        ]);

        $action = app(LicenseContentRightsToTerritoryAction::class);
        $license1 = $action->execute([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-OTT-01',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'minimum_guarantee_minor' => 50_000_000,
            'royalty_rate_pct' => 15.0,
            'start_date' => '2026-11-01',
            'end_date' => '2027-11-01',
        ]);

        expect($license1->id)->not->toBeNull()
            ->and($license1->territory)->toBe('ID');
    }

    public function test_explores_distribute_box_office_royalty_action_and_identifies_waterfall_gap(): void
    {
        $project = MedProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'PRJ-EXP-002',
            'title' => 'Box Office Hit',
            'genre' => 'ACTION',
            'phase' => 'RELEASED',
            'budget_limit_minor' => 1_000_000_000,
            'crew_cost_minor' => 400_000_000,
            'vendor_cost_minor' => 300_000_000,
            'studio_cost_minor' => 200_000_000,
            'total_production_cost_minor' => 900_000_000,
            'box_office_revenue_minor' => 0,
            'status' => 'RELEASED',
        ]);

        $talent = MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TC-EXP-001',
            'project_id' => $project->id,
            'talent_party_id' => 'ACTOR-JOHN-DOE',
            'role_name' => 'LEAD_ACTOR',
            'upfront_fee_minor' => 100_000_000,
            'backend_percentage' => 10.0,
            'calculated_royalty_minor' => 0,
            'payout_status' => 'HOLD',
        ]);

        $action = app(DistributeBoxOfficeAndStreamingRoyaltyAction::class);
        $totalRoyalties = $action->execute((string) $project->id, 200_000_000);

        expect($totalRoyalties)->toBe(20_000_000);
        $talent->refresh();
        expect($talent->calculated_royalty_minor)->toBe(20_000_000);
    }

    public function test_explores_settle_talent_residual_payout_action(): void
    {
        $talent = MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TC-EXP-002',
            'project_id' => (string) Str::uuid(),
            'talent_party_id' => 'ACTOR-JANE-DOE',
            'role_name' => 'DIRECTOR',
            'upfront_fee_minor' => 150_000_000,
            'backend_percentage' => 5.0,
            'calculated_royalty_minor' => 30_000_000,
            'payout_status' => 'APPROVED',
        ]);

        $action = app(SettleTalentResidualPayoutAction::class);
        $settled = $action->execute((string) $talent->id);

        expect($settled->payout_status)->toBe('PAID');
    }

    public function test_explores_book_programmatic_ad_inventory_action(): void
    {
        $channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-EXP-001',
            'name' => 'Smart Cinema Screen',
            'channel_type' => 'THEATRICAL',
            'revenue_share_pct' => 60.0,
            'status' => 'ACTIVE',
        ]);

        $action = app(BookProgrammaticAdInventoryCampaignAction::class);
        $campaign = $action->execute([
            'channel_id' => $channel->id,
            'advertiser_entity_id' => 'BRAND-COLA-01',
            'target_impressions' => 100_000,
            'floor_cpm_minor' => 50000,
            'actual_cpm_minor' => 75000,
        ]);

        expect($campaign->id)->not->toBeNull()
            ->and($campaign->total_spend_minor)->toBe(7_500_000);
    }

    public function test_explores_verify_digital_asset_watermark_chain_action(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-EXP-WM',
            'title' => 'Watermark Test IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
            'status' => 'ACTIVE',
        ]);

        $validHash = hash('sha256', "{$ip->id}:{$ip->ip_code}:{$ip->owner_entity_id}");
        $action = app(VerifyDigitalAssetWatermarkChainAction::class);

        $resultValid = $action->execute((string) $ip->id, $validHash);
        expect($resultValid['is_valid'])->toBeTrue();

        $resultInvalid = $action->execute((string) $ip->id, 'invalid_hash_value');
        expect($resultInvalid['is_valid'])->toBeFalse();
    }

    public function test_explores_studio_and_content_production_service(): void
    {
        $service = app(StudioAndContentProductionService::class);
        $studio = $service->createStudio([
            'name' => 'Studio 1 Sound Stage',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 5_000_000,
            'full_day_rate_minor' => 40_000_000,
        ]);

        expect($studio->id)->not->toBeNull()
            ->and($studio->name)->toBe('Studio 1 Sound Stage');
    }

    public function test_explores_distribution_and_advertising_service(): void
    {
        $service = app(DistributionAndAdvertisingService::class);
        $channel = $service->createChannel([
            'name' => 'Super App OTT Stream',
            'channel_type' => 'STREAMING',
            'revenue_share_pct' => 70.0,
        ]);

        $campaign = $service->bookCampaign([
            'advertiser_entity_id' => 'ADV-ECOMM-01',
            'channel_id' => $channel->id,
            'target_impressions' => 500_000,
            'floor_cpm_minor' => 40000,
            'actual_cpm_minor' => 50000,
        ]);

        $settled = $service->settleCampaignImpressions((string) $campaign->id, 400_000);
        expect($settled->verified_impressions)->toBe(400_000)
            ->and($settled->total_spend_minor)->toBe(20_000_000);
    }

    public function test_explores_med_audit_command_and_corruption_fixture(): void
    {
        $exitClean = Artisan::call('med:audit');
        expect($exitClean)->toBe(0);

        $fixture = new MedAuditCorruptionFixture;
        $fixture->corrupt();

        $exitCorrupt = Artisan::call('med:audit');
        expect($exitCorrupt)->toBe(1);
    }
}
