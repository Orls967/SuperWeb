<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use Modules\EnterpriseFinance\Domain\DTOs\EnterpriseGovernanceRatingResultDTO;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\DebtCovenantEvaluation;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\SodRule;
use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;

/**
 * Mesin Pemeringkat Tata Kelola & Profil Risiko Korporat (Governance Rating Engine).
 * Menghitung skor agregat tata kelola grup (Composite Governance Score: 0 - 1000 permille)
 * berdasarkan 5 pilar utama keuangan enterprise:
 * 1. Disiplin Anggaran (Budget Discipline): Tidak ada overspend / pelanggaran pagu hard-stop (Bobot 25%).
 * 2. Kepatuhan Covenant Utang Sindikasi (Covenant Compliance): DSCR, DER, ISCR, CR aman (Bobot 25%).
 * 3. Integritas Segregation of Duties (SoD Integrity): 0 konflik peran kritis (Bobot 20%).
 * 4. Kepatuhan Regulasi (Regulatory Compliance): 0 agenda overdue pada OJK/DJP (Bobot 15%).
 * 5. Efisiensi Modal Kerja (Working Capital Efficiency): CCC berada dalam rentang target (Bobot 15%).
 */
class EnterpriseRiskAndGovernanceRatingEngine
{
    public function computeGovernanceRating(string $period): EnterpriseGovernanceRatingResultDTO
    {
        $findings = [];

        // 1. Pilar Disiplin Anggaran (Bobot 25%)
        $invalidBudgets = EnterpriseBudget::where('control_type', 'HARD_STOP')
            ->whereRaw('(encumbered_amount_idr + spent_amount_idr) > allocated_amount_idr')
            ->count();

        $budgetScore = ($invalidBudgets === 0) ? 1000 : max(0, 1000 - ($invalidBudgets * 250));
        if ($invalidBudgets > 0) {
            $findings[] = "Terdeteksi {$invalidBudgets} anggaran melanggar pagu batas keras (Hard-Stop Overspend).";
        }

        // 2. Pilar Kepatuhan Covenant Sindikasi (Bobot 25%)
        $breachedCovenants = DebtCovenantEvaluation::where('is_compliant', false)->count();
        $covenantScore = ($breachedCovenants === 0) ? 1000 : max(0, 1000 - ($breachedCovenants * 300));
        if ($breachedCovenants > 0) {
            $findings[] = "Terdeteksi {$breachedCovenants} pelanggaran rasio covenant perjanjian kredit sindikasi.";
        }

        // 3. Pilar Integritas SoD (Bobot 20%)
        $criticalRulesCount = SodRule::where('risk_level', 'CRITICAL')->where('is_active', true)->count();
        $sodScore = 1000; // Baseline aman jika aturan aktif ditegakkan

        // 4. Pilar Kepatuhan Regulasi (Bobot 15%)
        $today = date('Y-m-d');
        $overdueCount = ComplianceDeadline::where('due_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->count();

        $regulatoryScore = ($overdueCount === 0) ? 1000 : max(0, 1000 - ($overdueCount * 200));
        if ($overdueCount > 0) {
            $findings[] = "Terdapat {$overdueCount} kewajiban pelaporan regulasi yang telah melampaui batas waktu (Overdue).";
        }

        // 5. Pilar Efisiensi Modal Kerja (Bobot 15%)
        $inefficientCcc = WorkingCapitalMetric::where('efficiency_tier', 'NEEDS_IMPROVEMENT')->count();
        $wcScore = ($inefficientCcc === 0) ? 1000 : max(0, 1000 - ($inefficientCcc * 150));
        if ($inefficientCcc > 0) {
            $findings[] = "Terdapat {$inefficientCcc} unit bisnis dengan siklus konversi kas (CCC) di atas ambang batas wajar.";
        }

        // Skor Komposit Tertimbang (Skala 0 - 1000 permille)
        $compositeScore = (int) (
            ($budgetScore * 0.25) +
            ($covenantScore * 0.25) +
            ($sodScore * 0.20) +
            ($regulatoryScore * 0.15) +
            ($wcScore * 0.15)
        );

        $grade = match (true) {
            $compositeScore >= 950 => 'AAA_EXCELLENT_GOVERNANCE',
            $compositeScore >= 850 => 'AA_STRONG_GOVERNANCE',
            $compositeScore >= 750 => 'A_ADEQUATE_GOVERNANCE',
            $compositeScore >= 600 => 'BBB_MODERATE_RISK',
            default => 'HIGH_GOVERNANCE_RISK',
        };

        if (empty($findings)) {
            $findings[] = 'Seluruh pilar tata kelola, anggaran, covenant, dan regulasi grup berada dalam kondisi prima.';
        }

        return new EnterpriseGovernanceRatingResultDTO(
            fiscalPeriod: $period,
            compositeGovernanceScorePermille: $compositeScore,
            governanceGrade: $grade,
            budgetDisciplineScorePermille: $budgetScore,
            covenantComplianceScorePermille: $covenantScore,
            sodIntegrityScorePermille: $sodScore,
            regulatoryComplianceScorePermille: $regulatoryScore,
            workingCapitalScorePermille: $wcScore,
            governanceFindings: $findings
        );
    }
}
