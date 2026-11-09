<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Events\SloBurnRateExceededEvent;
use Modules\Platform\Domain\Models\ServiceSlo;
use Modules\Platform\Domain\Services\SreAlertRouter;

/**
 * Aksi evaluasi laju pembakaran error budget multi-jendela (1h, 6h, 24h, 3d, 14d, 30d).
 */
class EvaluateMultiWindowBurnRateAction
{
    public function __construct(
        protected SreAlertRouter $router = new SreAlertRouter()
    ) {
    }

    /**
     * @param array<string, float> $windowErrorRates Map window -> error_rate
     * @return array{service: string, burn_rates: array<string, float>, alert: array<string, string>}
     */
    public function evaluate(ServiceSlo $slo, array $windowErrorRates): array
    {
        $targetRatio = $slo->target_sli_bps / 10000.0;
        $allowedErrorRatio = max(0.000001, 1.0 - $targetRatio);

        $burnRates = [];
        foreach ($windowErrorRates as $window => $rate) {
            $burnRates[$window] = round($rate / $allowedErrorRatio, 2);
        }

        $b1h = $burnRates['1h'] ?? 0.0;
        $b6h = $burnRates['6h'] ?? 0.0;

        $alert = $this->router->evaluateBurnRateAlert($slo, $b1h, $b6h);

        $slo->burn_rate_1h = $b1h;
        $slo->burn_rate_6h = $b6h;
        $slo->status = $alert['urgency'] === 'critical' ? 'critical' : ($alert['urgency'] === 'major' ? 'warning' : 'healthy');
        $slo->save();

        return [
            'service' => $slo->service_name,
            'burn_rates' => $burnRates,
            'alert' => $alert,
        ];
    }
}

