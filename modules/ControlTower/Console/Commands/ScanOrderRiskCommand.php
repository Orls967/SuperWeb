<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Services\OrderRiskAssessmentService;

final class ScanOrderRiskCommand extends Command
{
    protected $signature = 'tower:risk-scan {--item= : Optional SKU item code to scan}';

    protected $description = 'Scan Control Tower order & item supply risks (0..1000 deterministic scorer)';

    public function handle(OrderRiskAssessmentService $service): int
    {
        $item = $this->option('item');
        $itemCode = is_string($item) && $item !== '' ? $item : null;

        $scores = $service->scanAll($itemCode);
        $criticalHigh = $scores->whereIn('risk_tier', ['CRITICAL', 'HIGH'])->count();

        $this->info("Scanned {$scores->count()} order/item risk score(s) ({$criticalHigh} CRITICAL/HIGH).");

        return self::SUCCESS;
    }
}
