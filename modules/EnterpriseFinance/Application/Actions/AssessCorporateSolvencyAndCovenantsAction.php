<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantResultDTO;
use Modules\EnterpriseFinance\Domain\Models\DebtCovenantEvaluation;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;

/**
 * Aksi Penilaian Solvabilitas & Kepatuhan Covenant Sindikasi Grup (54.7 & D7).
 * Menghitung rasio finansial wajib (DSCR, DER, ISCR, Current Ratio), menerbitkan sertifikat digital,
 * dan menyimpan rekam jejak kepatuhan perjanjian kredit bank sindikasi.
 */
class AssessCorporateSolvencyAndCovenantsAction
{
    public function __construct(
        protected readonly SyndicatedLoanCovenantCalculator $calculator
    ) {}

    public function execute(
        SyndicatedFacility $facility,
        string $evaluationPeriod,
        int $ebitdaMinor,
        int $taxPaidMinor,
        int $debtServiceMinor,
        int $totalDebtMinor,
        int $totalEquityMinor,
        int $ebitMinor,
        int $interestExpenseMinor,
        int $currentAssetsMinor,
        int $currentLiabilitiesMinor,
        ?int $userId = null
    ): SyndicatedLoanCovenantResultDTO {
        $input = new SyndicatedLoanCovenantInputDTO(
            facilityCode: $facility->facility_code,
            evaluationPeriod: $evaluationPeriod,
            ebitdaMinor: $ebitdaMinor,
            taxPaidMinor: $taxPaidMinor,
            debtServiceMinor: $debtServiceMinor,
            totalInterestBearingDebtMinor: $totalDebtMinor,
            totalEquityMinor: $totalEquityMinor,
            ebitMinor: $ebitMinor,
            interestExpenseMinor: $interestExpenseMinor,
            currentAssetsMinor: $currentAssetsMinor,
            currentLiabilitiesMinor: $currentLiabilitiesMinor
        );

        $res = $this->calculator->calculate($input);

        // Rekam hasil evaluasi ke tabel database
        DebtCovenantEvaluation::create([
            'facility_id' => $facility->id,
            'evaluation_period' => $evaluationPeriod,
            'dscr_permille' => $res->dscrPermille,
            'der_permille' => $res->derPermille,
            'iscr_permille' => $res->iscrPermille,
            'current_ratio_permille' => $res->currentRatioPermille,
            'is_compliant' => $res->isFullyCompliant,
            'sha256_signature' => $res->certificate->sha256Signature,
            'certificate_payload_json' => json_encode($res->toArray(), JSON_THROW_ON_ERROR),
            'evaluated_by' => $userId,
        ]);

        return $res;
    }
}
