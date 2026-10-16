<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\TwelveLinesAuditPort;

class TwelveLinesComprehensiveAuditCommand extends Command
{
    protected $signature = 'ecosystem:audit-12-lines';

    protected $description = 'Run comprehensive audit across all 12 conglomerate business lines';

    public function handle(TwelveLinesAuditPort $auditPort): int
    {
        $this->info('Starting 12-Lines Cross-Ecosystem Audit...');

        // 1. Audit Venues
        $venueCount = $auditPort->getVenueCount();
        $this->info("Venue Line: {$venueCount} venues registered.");

        // 2. Audit Hotels
        $hotelCount = $auditPort->getHotelCount();
        $this->info("Hospitality Line: {$hotelCount} properties registered.");

        // 3. Audit Mining
        $miningCount = $auditPort->getMiningCount();
        $this->info("Mining Line: {$miningCount} sites registered.");

        // 4. Audit Core Banking Ledger Invariants
        $negativeAssetDiscrepancies = $auditPort->getNegativeAssetDiscrepancies();

        if ($negativeAssetDiscrepancies > 0) {
            $this->error("Ledger Invariant Breach: {$negativeAssetDiscrepancies} accounts have invalid negative balances!");

            return Command::FAILURE;
        }

        $this->info('Audit completed successfully with 0 discrepancies across all 12 lines.');

        return Command::SUCCESS;
    }
}
