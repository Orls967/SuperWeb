<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class EnterpriseGovernanceRatingResultDTO
{
    /**
     * @param  list<string>  $governanceFindings
     */
    public function __construct(
        public string $fiscalPeriod,
        public int $compositeGovernanceScorePermille,
        public string $governanceGrade,
        public int $budgetDisciplineScorePermille,
        public int $covenantComplianceScorePermille,
        public int $sodIntegrityScorePermille,
        public int $regulatoryComplianceScorePermille,
        public int $workingCapitalScorePermille,
        public array $governanceFindings
    ) {}

    public function toArray(): array
    {
        return [
            'fiscal_period' => $this->fiscalPeriod,
            'composite_governance_score_permille' => $this->compositeGovernanceScorePermille,
            'governance_grade' => $this->governanceGrade,
            'budget_discipline_score_permille' => $this->budgetDisciplineScorePermille,
            'covenant_compliance_score_permille' => $this->covenantComplianceScorePermille,
            'sod_integrity_score_permille' => $this->sodIntegrityScorePermille,
            'regulatory_compliance_score_permille' => $this->regulatoryComplianceScorePermille,
            'working_capital_score_permille' => $this->workingCapitalScorePermille,
            'governance_findings' => $this->governanceFindings,
        ];
    }
}
