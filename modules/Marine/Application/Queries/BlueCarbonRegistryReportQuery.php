<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Queries;

use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\MangroveRestorationProject;

/**
 * Query service laporan portofolio proyek karbon biru dan agregasi stok serapan karbon (Fase 171).
 */
class BlueCarbonRegistryReportQuery
{
    /**
     * Dapatkan ringkasan eksekutif seluruh proyek restorasi mangrove dan kredit karbon terbit.
     *
     * @return array{
     *     total_active_projects: int,
     *     total_restoration_hectares: float,
     *     total_trees_planted: int,
     *     mean_survival_rate_percentage: float,
     *     total_issued_credits_tco2e: float,
     *     total_retired_credits_tco2e: float,
     *     net_marketable_inventory_tco2e: float,
     *     registry_standard_distribution: array<string, int>
     * }
     */
    public function getExecutiveSummary(): array
    {
        $projects = MangroveRestorationProject::with(['assessments', 'issuances'])->get();

        $totalProjects = $projects->count();
        $totalHa = (float) $projects->sum('restoration_area_hectares');
        $totalTrees = (int) $projects->sum('trees_planted_count');
        $meanSurvival = $totalProjects > 0 ? (float) $projects->avg('survival_rate_percentage') : 0.0;

        $issuances = BlueCarbonCreditIssuance::all();
        $issuedCredits = (float) $issuances->sum('carbon_credits_tco2e');
        $retiredCredits = (float) $issuances->sum('credits_retired_tco2e');
        $netInventory = max(0.0, round($issuedCredits - $retiredCredits, 2));

        $standards = [];
        foreach ($issuances as $iss) {
            $std = (string) $iss->credit_standard;
            $standards[$std] = ($standards[$std] ?? 0) + 1;
        }

        return [
            'total_active_projects' => $totalProjects,
            'total_restoration_hectares' => round($totalHa, 2),
            'total_trees_planted' => $totalTrees,
            'mean_survival_rate_percentage' => round($meanSurvival, 2),
            'total_issued_credits_tco2e' => round($issuedCredits, 2),
            'total_retired_credits_tco2e' => round($retiredCredits, 2),
            'net_marketable_inventory_tco2e' => $netInventory,
            'registry_standard_distribution' => $standards,
        ];
    }
}
