<?php

declare(strict_types=1);

namespace Modules\Med\Console\Commands;

use Illuminate\Console\Command;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedProject;
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

        // Check severe budget overruns in completed projects
        $overrunProjects = MedProject::where('phase', 'COMPLETED')
            ->whereColumn('total_production_cost_minor', '>', 'budget_limit_minor')
            ->count();
        if ($overrunProjects > 0) {
            $this->warn("Found {$overrunProjects} completed projects exceeding approved production budgets.");
            $discrepancies += $overrunProjects;
        }

        // Check negative calculated royalties
        $invalidRoyalties = MedTalentContract::where('calculated_royalty_minor', '<', 0)->count();
        if ($invalidRoyalties > 0) {
            $this->warn("Found {$invalidRoyalties} talent contracts with negative royalty balance.");
            $discrepancies += $invalidRoyalties;
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
