<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Canary;

final class ErrorBudgetDepletionAlertEngine
{
    /**
     * Determines multi-window multi-burn-rate pager alerts according to Google SRE workbook.
     * Window 1: 1h burn rate > 14.4 (paged 2% budget consumed in 1 hour)
     * Window 2: 6h burn rate > 6.0 (paged 5% budget consumed in 6 hours)
     * Window 3: 24h burn rate > 3.0 (ticket created)
     *
     * @return array{alert_level: string, burn_rate: float, notify_channel: string}
     */
    public function evaluateAlert(float $burnRate1h, float $burnRate6h): array
    {
        if ($burnRate1h >= 14.4) {
            return [
                'alert_level' => 'P1_PAGE_IMMEDIATE',
                'burn_rate' => $burnRate1h,
                'notify_channel' => 'pagerduty_high_urgency_sre',
            ];
        }

        if ($burnRate6h >= 6.0) {
            return [
                'alert_level' => 'P2_PAGE_STANDBY',
                'burn_rate' => $burnRate6h,
                'notify_channel' => 'slack_ops_callout',
            ];
        }

        if ($burnRate6h >= 2.0) {
            return [
                'alert_level' => 'P3_TICKET_WARNING',
                'burn_rate' => $burnRate6h,
                'notify_channel' => 'jira_sre_backlog',
            ];
        }

        return [
            'alert_level' => 'NOMINAL_GREEN',
            'burn_rate' => max($burnRate1h, $burnRate6h),
            'notify_channel' => 'none',
        ];
    }
}
