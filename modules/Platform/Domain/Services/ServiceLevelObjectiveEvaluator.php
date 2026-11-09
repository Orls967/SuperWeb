<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\ServiceSlo;

/**
 * Evaluator Keandalan SLO multi-window untuk pemantauan error budget bulanan.
 */
class ServiceLevelObjectiveEvaluator
{
    /**
     * @param list<array{timestamp: int, is_error: bool}> $events
     * @return array{sli_bps: int, total_requests: int, error_requests: int, meets_slo: bool}
     */
    public function evaluateWindow(ServiceSlo $slo, array $events): array
    {
        $total = count($events);
        if ($total === 0) {
            return [
                'sli_bps' => 10000,
                'total_requests' => 0,
                'error_requests' => 0,
                'meets_slo' => true,
            ];
        }

        $errors = 0;
        foreach ($events as $e) {
            if ($e['is_error']) {
                $errors++;
            }
        }

        $successRatio = (float) (($total - $errors) / $total);
        $sliBps = (int) round($successRatio * 10000);

        return [
            'sli_bps' => $sliBps,
            'total_requests' => $total,
            'error_requests' => $errors,
            'meets_slo' => $sliBps >= $slo->target_sli_bps,
        ];
    }
}

