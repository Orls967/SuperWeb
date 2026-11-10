<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificateVerifier;
use Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\CovenantBreachException;
use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class SyndicatedLoanCovenantAlgorithmVectorTest extends TestCase
{
    private SyndicatedLoanCovenantCalculator $calculator;

    private SyndicatedLoanCovenantCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SyndicatedLoanCovenantCalculator;
        $this->verifier = new SyndicatedLoanCovenantCertificateVerifier;
    }

    public function test_syndicated_loan_covenant_calculator_matches_all_prompt_vectors(): void
    {
        $timestamp = CarbonImmutable::parse('2026-11-11T07:00:00Z');

        // Vektor 1: Fasilitas Sehat & Compliant Penuh (Tranche A Holding)
        $input1 = new SyndicatedLoanCovenantInputDTO(
            facilityCode: 'FAC-SYND-2026-01',
            evaluationPeriod: '2026-Q3',
            ebitdaMinor: 500_000_000_000,
            taxPaidMinor: 50_000_000_000,
            debtServiceMinor: 250_000_000_000,
            totalInterestBearingDebtMinor: 1_200_000_000_000,
            totalEquityMinor: 800_000_000_000,
            ebitMinor: 420_000_000_000,
            interestExpenseMinor: 140_000_000_000,
            currentAssetsMinor: 650_000_000_000,
            currentLiabilitiesMinor: 450_000_000_000
        );

        $res1 = $this->calculator->calculate($input1, $timestamp);

        // DSCR = (500M - 50M) * 1000 / 250M = 450M * 1000 / 250M = 1800 permille
        $this->assertSame(1800, $res1->dscrPermille);
        $this->assertTrue($res1->isDscrCompliant);

        // DER = 1200M * 1000 / 800M = 1500 permille (<= 2500)
        $this->assertSame(1500, $res1->derPermille);
        $this->assertTrue($res1->isDerCompliant);

        // ISCR = 420M * 1000 / 140M = 3000 permille (>= 2000)
        $this->assertSame(3000, $res1->iscrPermille);
        $this->assertTrue($res1->isIscrCompliant);

        // CR = 650M * 1000 / 450M = 1444 permille (>= 1100)
        $this->assertSame(1444, $res1->currentRatioPermille);
        $this->assertTrue($res1->isCurrentRatioCompliant);

        $this->assertTrue($res1->isFullyCompliant);
        $this->assertTrue($this->verifier->verify($res1->certificate));

        // Vektor 2: Pelanggaran DER (Leverage Berlebih)
        $input2 = new SyndicatedLoanCovenantInputDTO(
            facilityCode: 'FAC-SYND-2026-02',
            evaluationPeriod: '2026-Q3',
            ebitdaMinor: 300_000_000_000,
            taxPaidMinor: 30_000_000_000,
            debtServiceMinor: 200_000_000_000,
            totalInterestBearingDebtMinor: 2_600_000_000_000, // Utang tinggi
            totalEquityMinor: 800_000_000_000,               // DER = 3250 permille > 2500
            ebitMinor: 250_000_000_000,
            interestExpenseMinor: 100_000_000_000,
            currentAssetsMinor: 500_000_000_000,
            currentLiabilitiesMinor: 400_000_000_000
        );

        $res2 = $this->calculator->calculate($input2, $timestamp);
        $this->assertSame(3250, $res2->derPermille);
        $this->assertFalse($res2->isDerCompliant);
        $this->assertFalse($res2->isFullyCompliant);

        // Mode throw melempar CovenantBreachException
        $this->expectException(CovenantBreachException::class);
        $this->calculator->calculate($input2, $timestamp, throwOnBreach: true);
    }

    public function test_corrupted_covenant_certificate_signature_throws_exception(): void
    {
        $input = new SyndicatedLoanCovenantInputDTO(
            facilityCode: 'FAC-TEST',
            evaluationPeriod: '2026-Q3',
            ebitdaMinor: 100_000_000,
            taxPaidMinor: 10_000_000,
            debtServiceMinor: 50_000_000,
            totalInterestBearingDebtMinor: 150_000_000,
            totalEquityMinor: 100_000_000,
            ebitMinor: 80_000_000,
            interestExpenseMinor: 30_000_000,
            currentAssetsMinor: 120_000_000,
            currentLiabilitiesMinor: 100_000_000
        );

        $res = $this->calculator->calculate($input);

        // Manipulasi payload sertifikat secara curang
        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['dscr_permille'] = 9999;

        $tamperedCert = new SyndicatedLoanCovenantCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $this->verifier->verify($tamperedCert);
    }
}
