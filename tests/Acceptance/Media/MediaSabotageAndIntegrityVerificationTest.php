<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Med\Application\Services\AdImpressionService;
use Modules\Med\Application\Services\IpLicensingService;
use Modules\Med\Application\Services\IpRightsRegistryService;
use Modules\Med\Application\Services\RoyaltyWaterfallService;
use Modules\Med\Application\Services\StudioBookingService;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedAdPlacement;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedIpRightsShare;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Med\Domain\Models\MedRoyaltyStatementLine;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioBooking;
use Modules\Med\Domain\Models\MedStudioSlotClaim;
use Tests\TestCase;

/**
 * L15.5: Self-Sabotage & Mutation Integrity Verification Test for Lini 15 (Media & Kreatif).
 * Proves that all sabotage attempts across waterfall recoupment, interval half-open boundaries,
 * stale share transitions, penny absorption, velocity fraud, and audit invariants are caught.
 */
class MediaSabotageAndIntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected StudioBookingService $bookingService;

    protected IpRightsRegistryService $rightsService;

    protected IpLicensingService $licensingService;

    protected RoyaltyWaterfallService $waterfallService;

    protected AdImpressionService $adService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->bookingService = app(StudioBookingService::class);
        $this->rightsService = app(IpRightsRegistryService::class);
        $this->licensingService = app(IpLicensingService::class);
        $this->waterfallService = app(RoyaltyWaterfallService::class);
        $this->adService = app(AdImpressionService::class);
    }

    public function test_sabotage_waterfall_recoupment_cannot_exceed_available_advance_balance(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-SAB-REC-01',
            'title' => 'Recoupment Sabotage IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'LABEL-SAB',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-ARTIST', 'share_bps' => 10000],
            ],
        ]);

        $lic = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'LICENSEE-STREAM',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 15.0,
            'idempotency_key' => 'SAB:LIC:REC:01',
        ]);

        // Advance is 10,000,000 IDR
        $this->waterfallService->recordAdvance([
            'license_id' => $lic->id,
            'rightsholder_party_id' => 'PARTY-ARTIST',
            'advance_amount_minor' => 10_000_000,
            'idempotency_key' => 'SAB:ADV:01',
        ]);

        // Gross is 50,000,000 IDR. Net pool is 50,000,000 IDR.
        // Recoupment must be exactly 10,000,000 (not more), leaving 40,000,000 net payable.
        $stmt = $this->waterfallService->settleRoyalty([
            'license_id' => $lic->id,
            'billing_period' => '2026-01',
            'gross_revenue_minor' => 50_000_000,
            'distribution_fee_bps' => 0,
            'idempotency_key' => 'SAB:STMT:REC:01',
        ]);

        $line = MedRoyaltyStatementLine::where('statement_id', $stmt->id)->first();
        expect($line->recoupment_deducted_minor)->toBe(10_000_000)
            ->and($line->net_payable_minor)->toBe(40_000_000)
            ->and($stmt->total_recouped_minor)->toBe(10_000_000)
            ->and($stmt->total_royalty_distributed_minor)->toBe(40_000_000);
    }

    public function test_sabotage_closed_interval_boundary_point_does_not_falsely_block_adjacent_exclusive_licenses(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-SAB-INT-01',
            'title' => 'Interval Sabotage IP',
            'ip_type' => 'FILM',
            'owner_entity_id' => 'STUDIO-SAB',
        ]);

        // License 1: [2026-01-01, 2026-06-01)
        $lic1 = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'DISTRIBUTOR-H1',
            'rights_type' => 'THEATRICAL',
            'channel' => 'CINEMA',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
            'is_exclusive' => true,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'SAB:LIC:ADJ:01',
        ]);

        // License 2 starts on exact boundary date 2026-06-01: [2026-06-01, 2026-12-31)
        // Under half-open semantics, this MUST succeed without false conflict!
        $lic2 = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'DISTRIBUTOR-H2',
            'rights_type' => 'THEATRICAL',
            'channel' => 'CINEMA',
            'territory' => 'ID',
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 20.0,
            'idempotency_key' => 'SAB:LIC:ADJ:02',
        ]);

        expect($lic1->id)->not->toBeNull()
            ->and($lic2->id)->not->toBeNull();
    }

    public function test_sabotage_effective_date_transition_uses_new_shares_not_stale_shares(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-SAB-SHARE-01',
            'title' => 'Share Transition Sabotage IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'LABEL-SAB-2',
        ]);

        // Phase 1 (From Jan 1): 50/50
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'CREATOR-ORIG-1', 'share_bps' => 5000],
                ['rightsholder_party_id' => 'CREATOR-ORIG-2', 'share_bps' => 5000],
            ],
        ]);

        // Phase 2 (From July 1): 80/20 renegotiated
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-07-01',
            'shares' => [
                ['rightsholder_party_id' => 'CREATOR-ORIG-1', 'share_bps' => 8000],
                ['rightsholder_party_id' => 'CREATOR-ORIG-2', 'share_bps' => 2000],
            ],
        ]);

        $lic = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'STREAM-SERVICE',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 15.0,
            'idempotency_key' => 'SAB:LIC:SHR:01',
        ]);

        // Settle Period 2026-08 (August): Must use the updated 80/20 share, NOT stale 50/50!
        $stmt = $this->waterfallService->settleRoyalty([
            'license_id' => $lic->id,
            'billing_period' => '2026-08',
            'gross_revenue_minor' => 100_000_000,
            'distribution_fee_bps' => 0,
            'idempotency_key' => 'SAB:STMT:AUG:01',
        ]);

        $lines = MedRoyaltyStatementLine::where('statement_id', $stmt->id)->get()->keyBy('rightsholder_party_id');
        expect($lines['CREATOR-ORIG-1']->net_payable_minor)->toBe(80_000_000)
            ->and($lines['CREATOR-ORIG-2']->net_payable_minor)->toBe(20_000_000);
    }

    public function test_sabotage_waterfall_with_penny_absorption_guarantees_exact_integer_sum(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-SAB-PENNY-01',
            'title' => 'Penny Sabotage IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'LABEL-PENNY',
        ]);

        // 3 parties: 3333, 3333, 3334 bps
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-A', 'share_bps' => 3333],
                ['rightsholder_party_id' => 'PARTY-B', 'share_bps' => 3333],
                ['rightsholder_party_id' => 'PARTY-C', 'share_bps' => 3334],
            ],
        ]);

        $lic = $this->licensingService->grantLicense([
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'STREAMER',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'idempotency_key' => 'SAB:LIC:PNY:01',
        ]);

        // Net distributable 100 IDR: Floor division yields 33, 33, 33 = 99 IDR.
        // Penny absorption ensures remainder (1 IDR) goes to last line -> 33, 33, 34 = 100 IDR!
        $stmt = $this->waterfallService->settleRoyalty([
            'license_id' => $lic->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 100,
            'distribution_fee_bps' => 0,
            'idempotency_key' => 'SAB:STMT:PNY:01',
        ]);

        $linesSum = (int) MedRoyaltyStatementLine::where('statement_id', $stmt->id)->sum('net_payable_minor');
        expect($linesSum)->toBe(100)
            ->and($stmt->total_royalty_distributed_minor)->toBe(100);
    }

    public function test_sabotage_velocity_flood_from_single_bot_ip_is_flagged_as_fraud(): void
    {
        $channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-SAB-AD-01',
            'name' => 'Channel Sabotage',
            'channel_type' => 'DIGITAL_BANNER',
            'revenue_share_pct' => 70.0,
            'status' => 'ACTIVE',
        ]);

        $placement = MedAdPlacement::create([
            'id' => (string) Str::uuid(),
            'channel_id' => $channel->id,
            'placement_code' => 'PLC-SAB-01',
            'location_name' => 'Placement Sabotage',
            'floor_cpm_minor' => 50000,
            'is_active' => true,
        ]);

        $campaign = $this->adService->bookCampaign([
            'placement_id' => $placement->id,
            'advertiser_entity_id' => 'ADV-SAB-01',
            'target_impressions' => 10000,
            'actual_cpm_minor' => 60000,
            'idempotency_key' => 'SAB:CMP:VEL:01',
        ]);

        // Ingest 25 impressions from exact same device_ip in same second
        $fraudFlags = [];
        for ($i = 1; $i <= 25; $i++) {
            $imp = $this->adService->ingestImpression([
                'campaign_id' => $campaign->id,
                'impression_hash' => "BOT-IMP-{$i}",
                'device_ip_hash' => 'BOTNET-ATTACKER-IP',
                'timestamp_utc' => '2026-11-01 12:00:00',
            ]);
            $fraudFlags[$i] = $imp->is_fraud;
        }

        // First 10 are allowed, impressions 11 through 25 MUST be flagged as fraud
        for ($v = 1; $v <= 10; $v++) {
            expect($fraudFlags[$v])->toBeFalse();
        }
        for ($f = 11; $f <= 25; $f++) {
            expect($fraudFlags[$f])->toBeTrue();
        }
    }

    public function test_sabotage_tampered_royalty_statement_is_caught_by_med_audit(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-SAB-AUD-01',
            'title' => 'Audit Sabotage IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'LABEL-AUD',
            'status' => 'ACTIVE',
        ]);

        $lic = MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-SAB-AUD-01',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-AUD',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 15.0,
            'status' => 'ACTIVE',
        ]);

        $stmt = MedRoyaltyStatement::create([
            'id' => (string) Str::uuid(),
            'statement_number' => 'STMT-SAB-AUD-01',
            'license_id' => $lic->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 100_000_000,
            'total_distribution_fee_minor' => 15_000_000,
            'total_recouped_minor' => 0,
            'total_royalty_distributed_minor' => 85_000_000,
            'status' => 'ISSUED',
        ]);

        // Sabotage: Alter total_royalty_distributed_minor via raw DB update
        DB::table('med_royalty_statements')->where('id', $stmt->id)->update([
            'total_royalty_distributed_minor' => 999_999_999,
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_illegal_exclusive_license_overlap_injected_via_db_is_caught_by_med_audit(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-SAB-AUD-02',
            'title' => 'Audit Sabotage Overlap IP',
            'ip_type' => 'FILM',
            'owner_entity_id' => 'LABEL-AUD-2',
            'status' => 'ACTIVE',
        ]);

        MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-SAB-EXCL-1',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-1',
            'rights_type' => 'THEATRICAL',
            'channel' => 'CINEMA',
            'territory' => 'ID',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 15.0,
            'status' => 'ACTIVE',
        ]);

        // Directly inject overlapping license via raw DB insert
        MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-SAB-EXCL-2',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'PARTY-2',
            'rights_type' => 'THEATRICAL',
            'channel' => 'CINEMA',
            'territory' => 'ID',
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'is_exclusive' => true,
            'royalty_rate_pct' => 15.0,
            'status' => 'ACTIVE',
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_corrupted_copyright_share_sum_is_caught_by_med_audit(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-SAB-AUD-03',
            'title' => 'Audit Sabotage Share IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'LABEL-AUD-3',
            'status' => 'ACTIVE',
        ]);

        // Directly insert share summing to 9,500 bps via raw DB insert
        MedIpRightsShare::create([
            'id' => (string) Str::uuid(),
            'ip_id' => $ip->id,
            'rights_type' => 'STREAMING',
            'rightsholder_party_id' => 'PARTY-INCOMPLETE',
            'share_bps' => 9500,
            'effective_date' => '2026-01-01',
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_campaign_billed_impressions_mismatch_is_caught_by_med_audit(): void
    {
        $channel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-SAB-AUD-04',
            'name' => 'Channel Sabotage 4',
            'channel_type' => 'DIGITAL_BANNER',
            'revenue_share_pct' => 70.0,
            'status' => 'ACTIVE',
        ]);

        $campaign = MedAdCampaign::create([
            'id' => (string) Str::uuid(),
            'campaign_code' => 'CMP-SAB-AUD-04',
            'advertiser_entity_id' => 'ADV-SAB-04',
            'channel_id' => $channel->id,
            'target_impressions' => 100_000,
            'verified_impressions' => 80_000, // Fabricated: claims 80k
            'floor_cpm_minor' => 50000,
            'actual_cpm_minor' => 60000,
            'total_spend_minor' => 4_800_000,
            'status' => 'COMPLETED',
        ]);

        // Only insert 5 verified impressions in fact table
        for ($i = 1; $i <= 5; $i++) {
            DB::table('med_ad_impressions')->insert([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'impression_hash' => "SAB-AUD-IMP-{$i}",
                'device_ip_hash' => "DEV-IP-{$i}",
                'timestamp_utc' => '2026-10-01 10:00:00',
                'is_verified' => 1,
                'is_fraud' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_duplicate_studio_slot_claims_is_caught_by_med_audit(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-SAB-AUD-05',
            'name' => 'Studio Sabotage 5',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $booking = MedStudioBooking::create([
            'id' => (string) Str::uuid(),
            'booking_code' => 'BKG-SAB-AUD-05',
            'studio_id' => $studio->id,
            'client_entity_id' => 'PARTY-CLIENT-SAB',
            'start_time' => '2026-11-01 10:00:00',
            'end_time' => '2026-11-01 11:00:00',
            'total_price_minor' => 2000000,
            'status' => 'CONFIRMED',
        ]);

        MedStudioSlotClaim::create([
            'id' => (string) Str::uuid(),
            'studio_id' => $studio->id,
            'slot_start' => '2026-11-01 10:00:00',
            'slot_end' => '2026-11-01 10:30:00',
            'booking_id' => $booking->id,
        ]);

        // Drop unique index temporarily to simulate unconstrained corruption
        try {
            DB::statement('DROP INDEX IF EXISTS med_slot_claims_stu_start_uniq');
        } catch (\Throwable $e) {
        }

        DB::table('med_studio_slot_claims')->insert([
            'id' => (string) Str::uuid(),
            'studio_id' => $studio->id,
            'slot_start' => '2026-11-01 10:00:00',
            'slot_end' => '2026-11-01 10:30:00',
            'booking_id' => $booking->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('med:audit');
        expect($exitCode)->toBe(1);
    }
}
