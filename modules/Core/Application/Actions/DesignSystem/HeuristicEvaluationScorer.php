<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Modules\Core\Domain\Models\UxFinding;

/**
 * Mesin kalkulasi evaluasi 10 Heuristik Nielsen dan Severity-Weighted Defect Index (SWDI).
 */
class HeuristicEvaluationScorer
{
    /**
     * Definisi resmi 10 Prinsip Heuristik Nielsen.
     */
    public const HEURISTICS = [
        'H1' => 'Visibility of system status',
        'H2' => 'Match between system and the real world',
        'H3' => 'User control and freedom',
        'H4' => 'Consistency and standards',
        'H5' => 'Error prevention',
        'H6' => 'Recognition rather than recall',
        'H7' => 'Flexibility and efficiency of use',
        'H8' => 'Aesthetic and minimalist design',
        'H9' => 'Help users recognize, diagnose, and recover from errors',
        'H10' => 'Help and documentation',
    ];

    /**
     * Bobot keparahan cacat (Severity weights).
     */
    public const SEVERITY_WEIGHTS = [
        0 => 0,   // Bukan masalah
        1 => 1,   // Kosmetik
        2 => 3,   // Minor
        3 => 7,   // Mayor
        4 => 15,  // Katastropik / Blocker
    ];

    /**
     * Evaluasi temuan cacat UX dan hitung skor indeks SWDI.
     *
     * @param  array<int, array{heuristic: string, severity: int, frequency?: string, title?: string, component?: string}>  $findings
     * @return array{total_findings: int, swdi_score: int, health_status: string, distribution_by_severity: array<int, int>, distribution_by_heuristic: array<string, int>, prioritized_issues: array<int, array<string, mixed>>}
     */
    public function scoreFindings(array $findings): array
    {
        $swdi = 0;
        $severityDist = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $heuristicDist = [];
        $prioritized = [];

        foreach (array_keys(self::HEURISTICS) as $hKey) {
            $heuristicDist[$hKey] = 0;
        }

        foreach ($findings as $finding) {
            $h = strtoupper(trim((string) ($finding['heuristic'] ?? 'H4')));
            if (! isset(self::HEURISTICS[$h])) {
                $h = 'H4';
            }

            $sev = (int) ($finding['severity'] ?? 2);
            if ($sev < 0 || $sev > 4) {
                $sev = 2;
            }

            $freq = strtolower((string) ($finding['frequency'] ?? 'common'));
            $freqFactor = match ($freq) {
                'pervasive' => 3,
                'common' => 2,
                default => 1,
            };

            $weight = self::SEVERITY_WEIGHTS[$sev];
            $defectScore = $weight * $freqFactor;
            $swdi += $defectScore;

            $severityDist[$sev]++;
            $heuristicDist[$h]++;

            $prioritized[] = [
                'heuristic' => $h,
                'heuristic_name' => self::HEURISTICS[$h],
                'severity' => $sev,
                'defect_score' => $defectScore,
                'title' => (string) ($finding['title'] ?? 'Temuan Heuristik'),
                'component' => (string) ($finding['component'] ?? 'Umum'),
            ];
        }

        // Urutkan cacat dari skor terbesar
        usort($prioritized, fn ($a, $b) => $b['defect_score'] <=> $a['defect_score']);

        $healthStatus = $this->determineHealthStatus($swdi);

        return [
            'total_findings' => count($findings),
            'swdi_score' => $swdi,
            'health_status' => $healthStatus,
            'distribution_by_severity' => $severityDist,
            'distribution_by_heuristic' => $heuristicDist,
            'prioritized_issues' => $prioritized,
        ];
    }

    /**
     * Hitung evaluasi dari temuan yang tersimpan di database model UxFinding.
     *
     * @return array<string, mixed>
     */
    public function scoreFromDatabase(): array
    {
        $findings = UxFinding::all();
        $mapped = [];

        foreach ($findings as $f) {
            $mapped[] = [
                'heuristic' => $f->heuristic_code ?? 'H4',
                'severity' => (int) $f->severity,
                'title' => $f->title,
                'component' => $f->component_name,
                'frequency' => 'common',
            ];
        }

        return $this->scoreFindings($mapped);
    }

    /**
     * Tentukan status kesehatan antarmuka berdasarkan skor SWDI.
     */
    protected function determineHealthStatus(int $swdi): string
    {
        if ($swdi === 0) {
            return 'EXCELLENT';
        }
        if ($swdi <= 15) {
            return 'GOOD';
        }
        if ($swdi <= 40) {
            return 'ACCEPTABLE';
        }
        if ($swdi <= 80) {
            return 'DEGRADED';
        }

        return 'CRITICAL';
    }
}
