<?php

declare(strict_types=1);

namespace App\Quality\Analyzers;

/**
 * Penganalisis Keterikatan Kelas (Class Coupling: Afferent Ca, Efferent Ce & Instability Index).
 */
final class ClassCouplingAnalyzer
{
    /**
     * @param  array<string, list<string>>  $dependencyGraph  Map of class -> dependencies
     * @return array{afferent_ca: int, efferent_ce: int, instability_index: float}
     */
    public function analyzeCoupling(string $className, array $dependencyGraph): array
    {
        // Efferent Coupling Ce: jumlah kelas luar yang digunakan oleh class ini
        $outgoing = $dependencyGraph[$className] ?? [];
        $ce = count(array_unique($outgoing));

        // Afferent Coupling Ca: jumlah kelas luar yang bergantung pada class ini
        $ca = 0;
        foreach ($dependencyGraph as $caller => $deps) {
            if ($caller !== $className && in_array($className, $deps, true)) {
                $ca++;
            }
        }

        // Instability Index I = Ce / (Ca + Ce)
        $total = $ca + $ce;
        $instability = $total > 0 ? round((float) ($ce / $total), 4) : 0.0;

        return [
            'afferent_ca' => $ca,
            'efferent_ce' => $ce,
            'instability_index' => $instability,
        ];
    }
}
