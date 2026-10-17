<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Console\Commands;

use Illuminate\Console\Command;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;

class AuditCloudKitchenCommand extends Command
{
    protected $signature = 'ck:audit';

    protected $description = 'Perform two-source verification of cloud kitchen capacities, catering subscriptions, and meal order billings';

    public function handle(CloudKitchenService $service): int
    {
        $this->info('Starting Cloud Kitchen & Corporate Catering Operations Audit...');

        $result = $service->auditCloudKitchen();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Kitchens Checked', $result['kitchen_count']],
                ['Subscriptions Checked', $result['subscription_count']],
                ['Orders Checked', $result['order_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Cloud kitchen audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Cloud kitchen audit PASSED cleanly.');

        return 0;
    }
}
