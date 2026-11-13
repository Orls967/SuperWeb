<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Services\AlertScannerService;

final class ScanDisruptionAlertsCommand extends Command
{
    protected $signature = 'tower:alerts-scan';

    protected $description = 'Scan echelon bottlenecks, overdue in-transit shipments, and high risk scores to raise deduplicated alerts';

    public function handle(AlertScannerService $scanner): int
    {
        $result = $scanner->scan();
        $total = count($result['alerts']);

        $this->info("Alert scan completed: {$result['created_count']} new alert(s), {$result['existing_count']} existing alert(s) ({$total} total).");

        return self::SUCCESS;
    }
}
