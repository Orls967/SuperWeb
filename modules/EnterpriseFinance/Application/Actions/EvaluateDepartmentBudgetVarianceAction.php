<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Domain\Algorithms\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceResultDTO;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

/**
 * Aksi Evaluasi Varians Anggaran Departemen & Penegakan Guardrail (54.1 & D9).
 * Mengevaluasi realisasi belanja terhadap pagu alokasi, menghitung tingkat penyerapan (permille),
 * serta menentukan apakah departemen beroperasi dalam batas aman atau memerlukan intervensi pembekuan.
 */
class EvaluateDepartmentBudgetVarianceAction
{
    public function __construct(
        protected readonly BudgetVarianceAnalyzer $analyzer
    ) {}

    public function execute(EnterpriseBudget $budget): BudgetVarianceResultDTO
    {
        $input = new BudgetVarianceInputDTO(
            departmentCode: $budget->cost_center_code,
            fiscalYear: $budget->fiscal_year,
            allocatedBudgetMinor: $budget->allocated_amount_idr,
            actualSpentMinor: $budget->spent_amount_idr,
            encumberedMinor: $budget->encumbered_amount_idr
        );

        return $this->analyzer->analyze($input);
    }

    /**
     * Mengevaluasi seluruh anggaran dalam satu tahun fiskal dan mengidentifikasi anomali.
     *
     * @return array{
     *     fiscal_year: string,
     *     total_budgets_analyzed: int,
     *     breached_budgets_count: int,
     *     high_utilization_count: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function executeYearlyReview(string $fiscalYear): array
    {
        $budgets = EnterpriseBudget::where('fiscal_year', $fiscalYear)->get();

        $breachedCount = 0;
        $highUtilCount = 0;
        $results = [];

        foreach ($budgets as $b) {
            $res = $this->execute($b);

            if ($res->isBreached) {
                $breachedCount++;
            } elseif ($res->utilizationPermille >= 900) {
                $highUtilCount++;
            }

            $results[] = $res->toArray();
        }

        return [
            'fiscal_year' => $fiscalYear,
            'total_budgets_analyzed' => $budgets->count(),
            'breached_budgets_count' => $breachedCount,
            'high_utilization_count' => $highUtilCount,
            'results' => $results,
        ];
    }
}
