<?php

declare(strict_types=1);

namespace App\Quality\Analyzers;

/**
 * Exporter metrik kopling kelas (Afferent/Efferent Coupling) per modul untuk analisis arsitektur.
 */
class ClassCouplingMetricExporter
{
    public function __construct(
        protected ClassCouplingAnalyzer $analyzer = new ClassCouplingAnalyzer
    ) {}

    /**
     * @param  array<string, list<string>>  $moduleClasses  Map module -> list of classes
     * @param  array<string, list<string>>  $dependencyGraph  Map class -> dependencies
     * @return array<string, array{average_ca: float, average_ce: float, average_instability: float}>
     */
    public function exportModuleAverages(array $moduleClasses, array $dependencyGraph): array
    {
        $report = [];

        foreach ($moduleClasses as $mod => $classes) {
            $totalCa = 0;
            $totalCe = 0;
            $totalI = 0.0;
            $count = count($classes);

            foreach ($classes as $c) {
                $metrics = $this->analyzer->analyzeCoupling($c, $dependencyGraph);
                $totalCa += $metrics['afferent_ca'];
                $totalCe += $metrics['efferent_ce'];
                $totalI += $metrics['instability_index'];
            }

            $report[$mod] = [
                'average_ca' => $count > 0 ? round($totalCa / $count, 2) : 0.0,
                'average_ce' => $count > 0 ? round($totalCe / $count, 2) : 0.0,
                'average_instability' => $count > 0 ? round($totalI / $count, 4) : 0.0,
            ];
        }

        return $report;
    }
}
