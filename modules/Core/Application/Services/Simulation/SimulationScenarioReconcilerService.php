<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Scenarios\ScenarioResult;

/**
 * Layanan rekonsiliasi dan evaluasi batas toleransi hasil skenario simulasi bisnis.
 */
final class SimulationScenarioReconcilerService
{
    /**
     * @param  array<string, array{min?: float, max?: float, expected?: float}>  $benchmarks
     * @return array{
     *     passed: bool,
     *     evaluations: array<string, array{status: string, actual: mixed, message: string}>
     * }
     */
    public function evaluateResult(ScenarioResult $result, array $benchmarks): array
    {
        $evaluations = [];
        $allPassed = true;

        foreach ($benchmarks as $metricKey => $rules) {
            $actual = $result->kpis[$metricKey] ?? null;

            if ($actual === null) {
                $evaluations[$metricKey] = [
                    'status' => 'MISSING',
                    'actual' => null,
                    'message' => "Metrik KPI '{$metricKey}' tidak ditemukan dalam hasil skenario.",
                ];
                $allPassed = false;

                continue;
            }

            $numericActual = (float) $actual;
            $status = 'OK';
            $msg = 'Sesuai ekspektasi.';

            if (isset($rules['min']) && $numericActual < $rules['min']) {
                $status = 'FAILED_MIN';
                $msg = "Nilai {$numericActual} berada di bawah batas minimum {$rules['min']}.";
                $allPassed = false;
            } elseif (isset($rules['max']) && $numericActual > $rules['max']) {
                $status = 'FAILED_MAX';
                $msg = "Nilai {$numericActual} melampaui batas maksimum {$rules['max']}.";
                $allPassed = false;
            }

            $evaluations[$metricKey] = [
                'status' => $status,
                'actual' => $actual,
                'message' => $msg,
            ];
        }

        return [
            'passed' => $allPassed,
            'evaluations' => $evaluations,
        ];
    }
}
