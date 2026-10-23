<?php

declare(strict_types=1);

namespace Modules\Proptech\Console\Commands;

use Illuminate\Console\Command;
use Modules\Proptech\Application\Services\FlexSpaceService;

class AuditFlexCommand extends Command
{
    protected $signature = 'prp:audit-flex';

    protected $description = 'Perform two-source verification of flex-space booking anti-overlap and ledger deposit balances';

    public function handle(FlexSpaceService $service): int
    {
        $this->info('Starting Two-Source Flex-Space & Co-Working Audit...');

        $result = $service->auditFlex();

        $this->line("Spaces Checked: {$result['spaces_checked']}");
        $this->line("Active Bookings Checked: {$result['bookings_checked']}");

        if (! $result['passed']) {
            $this->error('FAILED: Discrepancies detected in flex space bookings or ledger:');
            foreach ($result['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('PASS: All flex space booking time slots and ledger revenue entries are verified.');

        return 0;
    }
}
