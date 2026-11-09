<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\ServiceSlo;

/**
 * Router peringatan SRE multi-jendela berbasis Google SRE Handbook.
 */
class SreAlertRouter
{
    /**
     * @return array{urgency: string, channel: string, message: string}
     */
    public function evaluateBurnRateAlert(ServiceSlo $slo, float $burnRate1h, float $burnRate6h): array
    {
        // Kondisi 1: 14.4x burn rate dalam 1 jam (2% error budget habis dalam 1 jam) -> PagerDuty / Telepon
        if ($burnRate1h >= 14.4) {
            return [
                'urgency' => 'critical',
                'channel' => 'pagerduty_oncall',
                'message' => sprintf('CRITICAL: %s burn rate 1h mencapai %.1fx (> 14.4x). 2%% error budget habis dalam 1 jam!', $slo->service_name, $burnRate1h),
            ];
        }

        // Kondisi 2: 6x burn rate dalam 6 jam (5% budget habis dalam 6 jam) -> Slack Alert
        if ($burnRate6h >= 6.0) {
            return [
                'urgency' => 'major',
                'channel' => 'slack_alerts',
                'message' => sprintf('MAJOR: %s burn rate 6h mencapai %.1fx (> 6.0x). 5%% error budget habis dalam 6 jam.', $slo->service_name, $burnRate6h),
            ];
        }

        // Kondisi 3: 1x burn rate (budget habis pas 30 hari)
        if ($burnRate1h > 1.0) {
            return [
                'urgency' => 'warning',
                'channel' => 'email_digest',
                'message' => sprintf('WARNING: %s burn rate 1h berada pada %.2fx (> 1.0x).', $slo->service_name, $burnRate1h),
            ];
        }

        return [
            'urgency' => 'none',
            'channel' => 'none',
            'message' => sprintf('%s beroperasi normal dalam batas target SLI.', $slo->service_name),
        ];
    }
}
