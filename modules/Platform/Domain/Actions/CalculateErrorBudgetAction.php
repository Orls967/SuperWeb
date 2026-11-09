<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Models\ServiceSlo;
use Modules\Platform\Domain\ValueObjects\ErrorBudgetBalance;

class CalculateErrorBudgetAction
{
    /**
     * @return array{service: string, allowed_downtime_mins: int, burn_rate: float, budget_remaining_percent: float, status: string}
     */
    public function calculate(ServiceSlo $slo, float $windowErrorRate = 0.0001): array
    {
        $targetRatio = $slo->target_sli_bps / 10000.0;
        $allowedErrorRatio = 1.0 - $targetRatio;

        $burnRate = $allowedErrorRatio > 0 ? $windowErrorRate / $allowedErrorRatio : 0.0;
        $budgetRemaining = max(0.0, 100.0 - ($burnRate * 10.0));

        $status = $burnRate > 2.0 ? 'critical' : ($burnRate > 1.0 ? 'warning' : 'healthy');

        $slo->burn_rate_1h = round($burnRate, 2);
        $slo->status = $status;
        $slo->save();

        return [
            'service' => $slo->service_name,
            'allowed_downtime_mins' => $slo->error_budget_mins,
            'burn_rate' => round($burnRate, 2),
            'budget_remaining_percent' => round($budgetRemaining, 2),
            'status' => $status,
        ];
    }
}

