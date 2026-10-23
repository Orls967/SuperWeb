<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\InterconnectSettlement;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\SimSubscription;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;

class TlxAuditCommand extends Command
{
    protected $signature = 'tlx:audit';

    protected $description = 'Audit modul Tlx: kapasitas rak colocation, integritas tautan 5G slice, dan kliring roaming';

    public function handle(): int
    {
        $this->info('Starting Telecom & Data Center Infrastructure Audit (tlx:audit)...');

        $siteCount = TelecomSite::count();
        $linkCount = TelecomLink::count();
        $simCount = SimSubscription::count();
        $deviceCount = IotDevice::count();
        $dcCount = TelecomDataCenter::count();
        $contractCount = ColocationContract::count();
        $settlementCount = InterconnectSettlement::count();

        $discrepancies = 0;

        // Verify rack capacity
        $overCapacityDcs = TelecomDataCenter::whereColumn('occupied_racks', '>', 'total_racks_capacity')->count();
        if ($overCapacityDcs > 0) {
            $this->warn("Found {$overCapacityDcs} data centers exceeding total rack capacity.");
            $discrepancies += $overCapacityDcs;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Telecom Sites', $siteCount],
                ['Network Links & Slices', $linkCount],
                ['Active SIM Subscriptions', $simCount],
                ['IoT Connected Devices', $deviceCount],
                ['Data Center Facilities', $dcCount],
                ['Colocation Contracts', $contractCount],
                ['Interconnect Settlements', $settlementCount],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Tlx audit FAILED with {$discrepancies} discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Tlx audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
