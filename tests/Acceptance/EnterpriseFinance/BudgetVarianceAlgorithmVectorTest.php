<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Algorithms\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificateVerifier;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceInputDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetGuardrailBreachException;
use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class BudgetVarianceAlgorithmVectorTest extends TestCase
{
    private BudgetVarianceAnalyzer $analyzer;

    private BudgetVarianceCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new BudgetVarianceAnalyzer;
        $this->verifier = new BudgetVarianceCertificateVerifier;
    }

    public function test_budget_variance_analyzer_matches_all_prompt_vectors(): void
    {
        $timestamp = CarbonImmutable::parse('2026-11-11T07:00:00Z');

        // Vektor 1: Penggunaan Hemat (FAVORABLE, 70% utilisasi)
        $input1 = new BudgetVarianceInputDTO(
            departmentCode: 'DEP-HR-GA',
            fiscalYear: '2026',
            allocatedBudgetMinor: 100_000_000,
            actualSpentMinor: 60_000_000,
            encumberedMinor: 10_000_000, // Total komitmen = 70M
            varianceBreachThresholdPermille: 100
        );

        $res1 = $this->analyzer->analyze($input1, $timestamp);

        $this->assertSame(30_000_000, $res1->varianceAmountMinor);
        $this->assertSame(700, $res1->utilizationPermille);
        $this->assertSame(300, $res1->variancePermille);
        $this->assertSame('FAVORABLE', $res1->classification);
        $this->assertFalse($res1->isBreached);
        $this->assertTrue($this->verifier->verify($res1->certificate));

        // Vektor 2: Penggunaan On-Track (95% utilisasi)
        $input2 = new BudgetVarianceInputDTO(
            departmentCode: 'DEP-OPERATIONS',
            fiscalYear: '2026',
            allocatedBudgetMinor: 200_000_000,
            actualSpentMinor: 170_000_000,
            encumberedMinor: 20_000_000 // Total komitmen = 190M = 950 permille
        );

        $res2 = $this->analyzer->analyze($input2, $timestamp);
        $this->assertSame(950, $res2->utilizationPermille);
        $this->assertSame('ON_TRACK', $res2->classification);
        $this->assertFalse($res2->isBreached);

        // Vektor 3: Peringatan Overspend Dalam Batas Toleransi (UNFAVORABLE_WARNING, 105% utilisasi)
        $input3 = new BudgetVarianceInputDTO(
            departmentCode: 'DEP-MARKETING',
            fiscalYear: '2026',
            allocatedBudgetMinor: 100_000_000,
            actualSpentMinor: 105_000_000,
            encumberedMinor: 0,
            varianceBreachThresholdPermille: 100 // Toleransi hingga 1100 permille
        );

        $res3 = $this->analyzer->analyze($input3, $timestamp);
        $this->assertSame(1050, $res3->utilizationPermille);
        $this->assertSame('UNFAVORABLE_WARNING', $res3->classification);
        $this->assertFalse($res3->isBreached);

        // Vektor 4: Pelanggaran Kritis (CRITICAL_BREACH, 125% utilisasi > 110%)
        $input4 = new BudgetVarianceInputDTO(
            departmentCode: 'DEP-SPECIAL-PROJECTS',
            fiscalYear: '2026',
            allocatedBudgetMinor: 100_000_000,
            actualSpentMinor: 125_000_000,
            encumberedMinor: 0,
            varianceBreachThresholdPermille: 100
        );

        $res4 = $this->analyzer->analyze($input4, $timestamp);
        $this->assertSame(1250, $res4->utilizationPermille);
        $this->assertSame('CRITICAL_BREACH', $res4->classification);
        $this->assertTrue($res4->isBreached);

        // Mode throw melempar BudgetGuardrailBreachException
        $this->expectException(BudgetGuardrailBreachException::class);
        $this->analyzer->analyze($input4, $timestamp, throwOnBreach: true);
    }

    public function test_corrupted_budget_variance_certificate_throws_exception(): void
    {
        $input = new BudgetVarianceInputDTO(
            departmentCode: 'DEP-TEST',
            fiscalYear: '2026',
            allocatedBudgetMinor: 10_000_000,
            actualSpentMinor: 5_000_000
        );

        $res = $this->analyzer->analyze($input);

        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['utilization_permille'] = 100;

        $tamperedCert = new BudgetVarianceCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $this->verifier->verify($tamperedCert);
    }
}
