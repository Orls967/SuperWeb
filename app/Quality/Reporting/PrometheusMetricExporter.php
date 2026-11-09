<?php

declare(strict_types=1);

namespace App\Quality\Reporting;

use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use Modules\Platform\Domain\Models\ServiceSlo;

/**
 * Exporter metrik sistem dan platform ke format exposition text Prometheus OpenTelemetry.
 */
final class PrometheusMetricExporter
{
    /**
     * @param  iterable<PhaseMetricSnapshot>  $snapshots
     * @param  iterable<ServiceSlo>  $slos
     */
    public function exportMetrics(iterable $snapshots, iterable $slos): string
    {
        $lines = [];

        // Quality Phase Metrics
        $lines[] = '# HELP quality_phase_b1_loc Total pure logic lines of code per phase';
        $lines[] = '# TYPE quality_phase_b1_loc gauge';
        foreach ($snapshots as $s) {
            $lines[] = sprintf('quality_phase_b1_loc{phase="%s",status="%s"} %d', $s->phase_id, $s->status, $s->b1_loc);
        }

        $lines[] = '# HELP quality_phase_decision_density Ratio of decision branches to executable statements';
        $lines[] = '# TYPE quality_phase_decision_density gauge';
        foreach ($snapshots as $s) {
            $lines[] = sprintf('quality_phase_decision_density{phase="%s"} %.4f', $s->phase_id, $s->decision_density);
        }

        $lines[] = '# HELP quality_phase_duplicate_ratio Token duplication ratio';
        $lines[] = '# TYPE quality_phase_duplicate_ratio gauge';
        foreach ($snapshots as $s) {
            $lines[] = sprintf('quality_phase_duplicate_ratio{phase="%s"} %.4f', $s->phase_id, $s->duplicate_ratio);
        }

        // Service SLOs
        $lines[] = '# HELP platform_service_burn_rate_1h 1-hour error budget burn rate';
        $lines[] = '# TYPE platform_service_burn_rate_1h gauge';
        foreach ($slos as $slo) {
            $lines[] = sprintf('platform_service_burn_rate_1h{service="%s",tier="%s"} %.2f', $slo->service_name, $slo->tier, $slo->burn_rate_1h);
        }

        $lines[] = '# HELP platform_service_error_budget_mins Total error budget in minutes per month';
        $lines[] = '# TYPE platform_service_error_budget_mins gauge';
        foreach ($slos as $slo) {
            $lines[] = sprintf('platform_service_error_budget_mins{service="%s"} %d', $slo->service_name, $slo->error_budget_mins);
        }

        return implode("\n", $lines)."\n";
    }
}
