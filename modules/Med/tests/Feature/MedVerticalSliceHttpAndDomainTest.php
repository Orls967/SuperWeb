<?php

declare(strict_types=1);

namespace Modules\Med\tests\Feature;

use App\Models\User;
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
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedTalentContract;
use RuntimeException;
use Tests\TestCase;

class MedVerticalSliceHttpAndDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleStudioAndProject(): array
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-'.Str::random(5),
            'name' => 'Studio Stage A Soundstage',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta Selatan',
            'hourly_rate_minor' => 2500000,
            'full_day_rate_minor' => 20000000,
            'status' => 'AVAILABLE',
        ]);

        $project = MedProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'PRJ-FILM-'.Str::random(5),
            'title' => 'The Cybernetic Odyssey',
            'genre' => 'MOVIE',
            'phase' => 'BRIEF',
            'budget_limit_minor' => 0,
            'box_office_revenue_minor' => 0,
        ]);

        return [$studio, $project];
    }

    public function test_1_authorized_user_can_access_med_studios_index(): void
    {
        [$studio, $project] = $this->createSampleStudioAndProject();
        $producer = User::factory()->create(['role' => 'producer']);

        $response = $this->actingAs($producer)->get(route('med.studios.index'));
        $response->assertOk();
        $response->assertSeeText('Studio Content Production & Greenlight Pipeline');
        $response->assertSeeText($studio->studio_code);
    }

    public function test_2_greenlight_studio_production_budget_via_http(): void
    {
        [$studio, $project] = $this->createSampleStudioAndProject();
        $producer = User::factory()->create(['role' => 'producer']);

        $response = $this->actingAs($producer)->post(route('med.studios.greenlight', $project->id), [
            'budget_limit_minor' => 750000000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('PRE_PROD', $project->fresh()->phase);
        $this->assertSame(750000000, $project->fresh()->budget_limit_minor);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'MEDIA_STUDIO_BUDGET_RELEASE',
            'reference_id' => $project->project_code,
        ]);
    }

    public function test_3_greenlight_rejected_when_not_in_brief_phase(): void
    {
        [$studio, $project] = $this->createSampleStudioAndProject();
        $action = app(GreenlightStudioProductionBudgetAction::class);
        $action->execute($project->id, 500000000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already past BRIEF phase');

        $action->execute($project->id, 600000000);
    }

    public function test_4_license_content_rights_to_territory_with_minimum_guarantee(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-SHOW-'.Str::random(5),
            'title' => 'Indonesian Culinary Masters',
            'ip_type' => 'SHOW_FORMAT',
            'owner_entity_id' => 'PTY-STUDIO-HOLDING',
            'status' => 'ACTIVE',
        ]);

        $action = app(LicenseContentRightsToTerritoryAction::class);
        $license = $action->execute([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PTY-GLOBAL-OTT-01',
            'channel' => 'STREAMING',
            'territory' => 'SE_ASIA',
            'minimum_guarantee_minor' => 150000000,
            'royalty_rate_pct' => 20.0,
        ]);

        $this->assertSame('ACTIVE', $license->status);
        $this->assertSame(150000000, $license->minimum_guarantee_minor);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'MEDIA_IP_LICENSING_FEE',
            'reference_id' => $license->license_code,
        ]);
    }

    public function test_5_distribute_box_office_royalty_and_ledger(): void
    {
        [$studio, $project] = $this->createSampleStudioAndProject();

        $contract = MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TLT-'.Str::random(5),
            'project_id' => $project->id,
            'talent_party_id' => 'PTY-ACTOR-LEAD',
            'role_name' => 'LEAD_ACTOR',
            'upfront_fee_minor' => 100000000,
            'backend_percentage' => 5.0, // 5%
            'calculated_royalty_minor' => 0,
            'payout_status' => 'HOLD',
        ]);

        $action = app(DistributeBoxOfficeAndStreamingRoyaltyAction::class);
        $royalties = $action->execute($project->id, 200000000); // 5% of 200m = 10m

        $this->assertSame(10000000, $royalties);
        $this->assertSame(10000000, $contract->fresh()->calculated_royalty_minor);
        $this->assertSame('APPROVED', $contract->fresh()->payout_status);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'MEDIA_BOX_OFFICE_DISTRIBUTE',
            'reference_id' => $project->project_code,
        ]);
    }

    public function test_6_book_programmatic_ad_inventory_campaign(): void
    {
        $channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CHN-OOH-'.Str::random(5),
            'name' => 'Mall Atrium Digital LED Megatron',
            'channel_type' => 'DIGITAL_OOH',
            'revenue_share_pct' => 65.0,
            'status' => 'ACTIVE',
        ]);

        $action = app(BookProgrammaticAdInventoryCampaignAction::class);
        $campaign = $action->execute([
            'advertiser_entity_id' => 'PTY-CORP-BANK',
            'channel_id' => $channel->id,
            'target_impressions' => 100000,
            'actual_cpm_minor' => 70000, // 100k / 1k * 70k = 7,000,000
        ]);

        $this->assertSame(7000000, $campaign->total_spend_minor);
        $this->assertSame(4550000, $campaign->publisher_share_minor); // 65%
        $this->assertSame(2450000, $campaign->platform_share_minor);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'MEDIA_AD_CAMPAIGN_SETTLEMENT',
            'reference_id' => $campaign->campaign_code,
        ]);
    }

    public function test_7_verify_digital_asset_watermark_chain_and_settle_talent(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-TRACK-01',
            'title' => 'Cinematic Score Soundtrack',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'PTY-MUSIC-LABEL',
            'status' => 'ACTIVE',
        ]);

        $expectedHash = hash('sha256', "{$ip->id}:{$ip->ip_code}:{$ip->owner_entity_id}");

        $verifyAction = app(VerifyDigitalAssetWatermarkChainAction::class);
        $result = $verifyAction->execute($ip->id, $expectedHash);

        $this->assertTrue($result['is_valid']);
        $this->assertSame('PTY-MUSIC-LABEL', $result['owner']);

        // Settle talent residual
        [$studio, $project] = $this->createSampleStudioAndProject();
        $talentContract = MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TLT-SETTLE-'.Str::random(5),
            'project_id' => $project->id,
            'talent_party_id' => 'PTY-COMPOSER',
            'role_name' => 'COMPOSER',
            'upfront_fee_minor' => 50000000,
            'backend_percentage' => 3.0,
            'calculated_royalty_minor' => 15000000,
            'payout_status' => 'APPROVED',
        ]);

        $settleAction = app(SettleTalentResidualPayoutAction::class);
        $paid = $settleAction->execute($talentContract->id);

        $this->assertSame('PAID', $paid->payout_status);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'MEDIA_TALENT_RESIDUAL_PAYOUT',
            'reference_id' => $talentContract->contract_code,
        ]);
    }

    public function test_8_med_audit_command_and_security_rbac(): void
    {
        $this->createSampleStudioAndProject();

        $exitCode = Artisan::call('med:audit');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Media audit PASSED with 0 discrepancy', Artisan::output());

        // Unauthorized user 403
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('med.studios.index'))->assertForbidden();

        // Unauthenticated user 302
        auth()->logout();
        $this->get(route('med.studios.index'))->assertRedirect(route('login'));
    }
}
