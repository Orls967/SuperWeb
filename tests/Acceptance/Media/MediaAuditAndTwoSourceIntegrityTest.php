<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use App\Quality\Audit\Fixtures\MedAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedAdImpression;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedIpRightsShare;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioSlotClaim;
use Tests\TestCase;

class MediaAuditAndTwoSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_audit_passes_on_clean_state_with_zero_discrepancies(): void
    {
        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_audit_fails_on_corruption_fixture(): void
    {
        $fixture = new MedAuditCorruptionFixture;
        $fixture->corrupt();

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_detects_discrepancy_between_royalty_statement_and_formula(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-AUD-01',
            'title' => 'Audit IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
            'status' => 'ACTIVE',
        ]);

        $license = MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-AUD-01',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-AUD-01',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'status' => 'ACTIVE',
        ]);

        $stmt = MedRoyaltyStatement::create([
            'id' => (string) Str::uuid(),
            'statement_number' => 'STMT-AUD-01',
            'license_id' => $license->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 100_000_000,
            'total_distribution_fee_minor' => 15_000_000,
            'total_recouped_minor' => 0,
            'total_royalty_distributed_minor' => 85_000_000, // Valid
            'status' => 'ISSUED',
        ]);

        // Tamper royalty statement total via DB
        DB::table('med_royalty_statements')->where('id', $stmt->id)->update([
            'total_royalty_distributed_minor' => 99_000_000, // Corrupted!
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_detects_illegal_exclusive_license_overlap(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-AUD-02',
            'title' => 'Audit Overlap IP',
            'ip_type' => 'SHOW_FORMAT',
            'owner_entity_id' => 'ENT-STUDIO-01',
            'status' => 'ACTIVE',
        ]);

        MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-AUD-EXCL-1',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-A',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'status' => 'ACTIVE',
        ]);

        // Directly insert an overlapping exclusive license bypassing service
        MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-AUD-EXCL-2',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-B',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'ID',
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'is_exclusive' => true,
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_detects_invalid_rights_share_sum_not_10000_bps(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-AUD-03',
            'title' => 'Audit Share IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
            'status' => 'ACTIVE',
        ]);

        // Insert share summing to only 9000 bps
        MedIpRightsShare::create([
            'id' => (string) Str::uuid(),
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'rightsholder_party_id' => 'PARTY-X',
            'share_bps' => 9000,
            'effective_date' => '2026-01-01',
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_detects_campaign_billed_impressions_mismatch_with_facts(): void
    {
        $channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-AUD-01',
            'name' => 'Channel Audit',
            'channel_type' => 'DIGITAL_BANNER',
            'revenue_share_pct' => 70.0,
            'status' => 'ACTIVE',
        ]);

        $campaign = MedAdCampaign::create([
            'id' => (string) Str::uuid(),
            'campaign_code' => 'CMP-AUD-01',
            'advertiser_entity_id' => 'ADV-AUD',
            'channel_id' => $channel->id,
            'target_impressions' => 100_000,
            'verified_impressions' => 50_000, // Claims 50k
            'floor_cpm_minor' => 50000,
            'actual_cpm_minor' => 60000,
            'total_spend_minor' => 3_000_000,
            'status' => 'COMPLETED',
        ]);

        // Only insert 10 verified impressions in fact table -> mismatch!
        for ($i = 1; $i <= 10; $i++) {
            MedAdImpression::create([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'impression_hash' => "AUD-IMP-{$i}",
                'device_ip_hash' => "DEV-IP-{$i}",
                'timestamp_utc' => '2026-10-01 10:00:00',
                'is_verified' => true,
                'is_fraud' => false,
            ]);
        }

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_detects_duplicate_studio_slot_claims(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-AUD-01',
            'name' => 'Audit Studio',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        MedStudioSlotClaim::create([
            'id' => (string) Str::uuid(),
            'studio_id' => $studio->id,
            'slot_start' => '2026-11-01 09:00:00',
            'slot_end' => '2026-11-01 09:30:00',
            'booking_id' => (string) Str::uuid(),
        ]);

        // Second booking on exact same slot without unique constraint (raw DB)
        DB::table('med_studio_slot_claims')->insert([
            'id' => (string) Str::uuid(),
            'studio_id' => $studio->id,
            'slot_start' => '2026-11-01 09:00:00',
            'slot_end' => '2026-11-01 09:30:00',
            'booking_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_audit_command_contract_compliance(): void
    {
        $exitCode = Artisan::call('med:audit');
        expect(in_array($exitCode, [0, 1], true))->toBeTrue();
    }
}
