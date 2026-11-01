<?php

declare(strict_types=1);

namespace Modules\Med\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedAdImpression;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedIpRightsShare;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Med\Domain\Models\MedStudioBooking;
use Modules\Med\Domain\Models\MedTalentContract;

class MedAuditCommand extends Command
{
    protected $signature = 'med:audit';

    protected $description = 'Audit industri kreatif & media: integritas anggaran proyek studio, lisensi HAKI, dan kliring royalti talent';

    public function handle(): int
    {
        $this->info('Starting Media Studio, IP & Advertising Operations Audit (med:audit)...');

        $projectCount = MedProject::count();
        $bookingCount = MedStudioBooking::count();
        $ipCount = MedIpAsset::count();
        $licenseCount = MedIpLicense::count();
        $talentCount = MedTalentContract::count();
        $adCount = MedAdCampaign::count();

        $discrepancies = 0;

        // Check 1: Severe budget overruns in completed projects
        $overrunProjects = MedProject::where('phase', 'COMPLETED')
            ->whereColumn('total_production_cost_minor', '>', 'budget_limit_minor')
            ->count();
        if ($overrunProjects > 0) {
            $this->warn("Found {$overrunProjects} completed projects exceeding approved production budgets.");
            $discrepancies += $overrunProjects;
        }

        // Check 2: Negative calculated royalties in talent contracts
        $invalidRoyalties = MedTalentContract::where('calculated_royalty_minor', '<', 0)->count();
        if ($invalidRoyalties > 0) {
            $this->warn("Found {$invalidRoyalties} talent contracts with negative royalty balance.");
            $discrepancies += $invalidRoyalties;
        }

        // Check 3: Royalty statement formula consistency (gross - fee - recoupment == distributed)
        $statements = MedRoyaltyStatement::all();
        foreach ($statements as $stmt) {
            $expectedDistributed = (int) $stmt->gross_revenue_minor
                - (int) $stmt->total_distribution_fee_minor
                - (int) $stmt->total_recouped_minor;

            if ((int) $stmt->total_royalty_distributed_minor !== $expectedDistributed) {
                $this->warn("Royalty statement {$stmt->statement_number} distribution mismatch: expected {$expectedDistributed}, got {$stmt->total_royalty_distributed_minor}");
                $discrepancies++;
            }
        }

        // Check 4: Illegal exclusive license overlaps
        $exclusiveLicenses = MedIpLicense::where('is_exclusive', true)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($exclusiveLicenses as $lic) {
            $overlapCount = MedIpLicense::where('id', '!=', $lic->id)
                ->where('ip_id', $lic->ip_id)
                ->where('rights_type', $lic->rights_type)
                ->where('territory', $lic->territory)
                ->where('status', 'ACTIVE')
                ->where('start_date', '<', $lic->end_date)
                ->where('end_date', '>', $lic->start_date)
                ->count();

            if ($overlapCount > 0) {
                $this->warn("Exclusive license {$lic->license_code} overlaps with {$overlapCount} other license(s)");
                $discrepancies += $overlapCount;
            }
        }

        // Check 5: Invalid copyright share sums (must sum to 10,000 bps per ip, rights_type, effective_date)
        $shareGroups = MedIpRightsShare::select('ip_id', 'rights_type', 'effective_date', DB::raw('SUM(share_bps) as total_bps'))
            ->groupBy('ip_id', 'rights_type', 'effective_date')
            ->having('total_bps', '!=', 10000)
            ->get();

        if ($shareGroups->isNotEmpty()) {
            foreach ($shareGroups as $group) {
                $this->warn("IP {$group->ip_id} {$group->rights_type} share on {$group->effective_date} sums to {$group->total_bps} bps (expected 10,000 bps)");
                $discrepancies++;
            }
        }

        // Check 6: Campaign billed impressions vs verified impressions fact table
        $completedCampaigns = MedAdCampaign::where('status', 'COMPLETED')->get();
        foreach ($completedCampaigns as $campaign) {
            $factCount = MedAdImpression::where('campaign_id', $campaign->id)
                ->where('is_verified', true)
                ->where('is_fraud', false)
                ->count();

            if ((int) $campaign->verified_impressions !== $factCount) {
                $this->warn("Campaign {$campaign->campaign_code} billed verified impressions ({$campaign->verified_impressions}) does not match fact count ({$factCount})");
                $discrepancies++;
            }
        }

        // Check 7: Duplicate studio slot claims
        $slotDuplicates = DB::table('med_studio_slot_claims')
            ->select('studio_id', 'slot_start', DB::raw('count(*) as count'))
            ->groupBy('studio_id', 'slot_start')
            ->having('count', '>', 1)
            ->get();

        if ($slotDuplicates->isNotEmpty()) {
            foreach ($slotDuplicates as $dup) {
                $this->warn("Duplicate slot claim for studio {$dup->studio_id} at {$dup->slot_start} (count: {$dup->count})");
                $discrepancies += ($dup->count - 1);
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Media & Film Projects', $projectCount],
                ['Studio Bookings', $bookingCount],
                ['Registered IP Assets', $ipCount],
                ['Territorial IP Licenses', $licenseCount],
                ['Creator & Talent Contracts', $talentCount],
                ['Programmatic Ad Campaigns', $adCount],
                ['Integrity Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Media audit FAILED with {$discrepancies} integrity discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Media audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
