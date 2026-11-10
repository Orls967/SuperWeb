<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

/**
 * Pengelola Hierarki Cost Center & Agregasi Pagu Bertingkat (54.1).
 * Mengelompokkan departemen ke dalam struktur pohon korporat:
 * Holding Corp -> Sub-Holding Unit -> Operating Department.
 */
class DepartmentCostCenterHierarchyManager
{
    /**
     * Peta hierarki standar grup korporat.
     */
    private const HIERARCHY_TREE = [
        'HOLDING_CORP' => [
            'name' => 'PT Nusantara Induk Holding',
            'units' => [
                'TECH_DIVISION' => [
                    'name' => 'Divisi Teknologi Informasi & Cloud',
                    'cost_centers' => ['IT_INFRA', 'SOFTWARE_ENG', 'DATA_AI', 'CYBER_SEC'],
                ],
                'LOGISTICS_DIVISION' => [
                    'name' => 'Divisi Logistik & Rantai Pasok',
                    'cost_centers' => ['FLEET_OPS', 'WAREHOUSING', 'CUSTOMS_CLEARING', 'LAST_MILE'],
                ],
                'CORPORATE_SERVICES' => [
                    'name' => 'Divisi Layanan Korporat & Tata Kelola',
                    'cost_centers' => ['FINANCE_TREASURY', 'LEGAL_COMPLIANCE', 'HR_TALENT', 'PROCUREMENT'],
                ],
            ],
        ],
    ];

    /**
     * Mendapatkan daftar cost center bawahan dari divisi tertentu.
     *
     * @return string[]
     */
    public function getCostCentersForDivision(string $divisionKey): array
    {
        $units = self::HIERARCHY_TREE['HOLDING_CORP']['units'] ?? [];
        if (! isset($units[$divisionKey])) {
            throw new InvalidArgumentException("Divisi [{$divisionKey}] tidak terdaftar dalam hierarki korporat.");
        }

        return $units[$divisionKey]['cost_centers'];
    }

    /**
     * Menghitung total pagu alokasi, realisasi belanja, dan sisa pagu untuk suatu divisi.
     *
     * @return array{
     *     division_key: string,
     *     division_name: string,
     *     fiscal_year: string,
     *     total_allocated_idr: int,
     *     total_encumbered_idr: int,
     *     total_spent_idr: int,
     *     total_available_idr: int,
     *     overall_utilization_permille: int
     * }
     */
    public function aggregateDivisionBudget(string $divisionKey, string $fiscalYear): array
    {
        $costCenters = $this->getCostCentersForDivision($divisionKey);
        $divisionName = self::HIERARCHY_TREE['HOLDING_CORP']['units'][$divisionKey]['name'];

        $budgets = EnterpriseBudget::where('fiscal_year', $fiscalYear)
            ->whereIn('cost_center_code', $costCenters)
            ->get();

        $totalAllocated = 0;
        $totalEncumbered = 0;
        $totalSpent = 0;

        foreach ($budgets as $b) {
            $totalAllocated += (int) $b->allocated_amount_idr;
            $totalEncumbered += (int) $b->encumbered_amount_idr;
            $totalSpent += (int) $b->spent_amount_idr;
        }

        $totalUtilized = $totalEncumbered + $totalSpent;
        $totalAvailable = max(0, $totalAllocated - $totalUtilized);
        $utilizationPermille = ($totalAllocated > 0) ? (int) intdiv($totalUtilized * 1000, $totalAllocated) : 0;

        return [
            'division_key' => $divisionKey,
            'division_name' => $divisionName,
            'fiscal_year' => $fiscalYear,
            'total_allocated_idr' => $totalAllocated,
            'total_encumbered_idr' => $totalEncumbered,
            'total_spent_idr' => $totalSpent,
            'total_available_idr' => $totalAvailable,
            'overall_utilization_permille' => $utilizationPermille,
        ];
    }

    /**
     * Mendapatkan seluruh ringkasan divisi dalam satu tahun fiskal.
     *
     * @return list<array{division_key: string, division_name: string, allocated: int, spent: int, available: int}>
     */
    public function getAllDivisionsSummary(string $fiscalYear): array
    {
        $summaries = [];
        foreach (array_keys(self::HIERARCHY_TREE['HOLDING_CORP']['units']) as $divKey) {
            $data = $this->aggregateDivisionBudget($divKey, $fiscalYear);
            $summaries[] = [
                'division_key' => $data['division_key'],
                'division_name' => $data['division_name'],
                'allocated' => $data['total_allocated_idr'],
                'spent' => $data['total_spent_idr'],
                'available' => $data['total_available_idr'],
            ];
        }

        return $summaries;
    }
}
