<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use InvalidArgumentException;
use Modules\Treasury\Domain\Algorithms\Certificates\FxRevaluationCertificateVerifier;
use Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class FxRevaluationAlgorithmVectorTest extends TestCase
{
    public function test_fx_revaluation_calculator_matches_all_prompt_vectors(): void
    {
        $calculator = new FxRevaluationCalculator;

        // Vektor 1: Piutang USD 100.000 minor, carrying 15.500.000, kurs penutupan 15650/1
        // -> revalued 15.650.000; belum terealisasi +150.000 (untung)
        $res1 = $calculator->calculateRevaluation(
            foreignBalanceMinor: 100_000,
            carryingFunctionalIdr: 15_500_000,
            rate: new RationalRate(15650, 1),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(15_650_000, $res1->revaluedFunctionalIdr);
        $this->assertSame(150_000, $res1->unrealizedGainLossIdr);
        $this->assertTrue($res1->isGain());
        $this->assertTrue(FxRevaluationCertificateVerifier::verify($res1->certificate));

        // Vektor 2: Utang USD 50.000 minor, carrying 7.750.000, 15650/1
        // -> revalued 7.825.000; selisih 75.000 = RUGI 75.000
        $res2 = $calculator->calculateRevaluation(
            foreignBalanceMinor: 50_000,
            carryingFunctionalIdr: 7_750_000,
            rate: new RationalRate(15650, 1),
            currencyDecimals: 2,
            isAsset: false
        );
        $this->assertSame(7_825_000, $res2->revaluedFunctionalIdr);
        $this->assertSame(-75_000, $res2->unrealizedGainLossIdr);
        $this->assertTrue($res2->isLoss());
        $this->assertTrue(FxRevaluationCertificateVerifier::verify($res2->certificate));

        // Vektor 3: EUR 12.345 minor, kurs 346670/20 (= 17.333,5)
        // -> 12.345 * 346.670 / 2.000 = 2.139.820,575 -> 2.139.821
        $res3 = $calculator->calculateRevaluation(
            foreignBalanceMinor: 12_345,
            carryingFunctionalIdr: 2_000_000,
            rate: new RationalRate(346670, 20),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(2_139_821, $res3->revaluedFunctionalIdr);
        $this->assertTrue(FxRevaluationCertificateVerifier::verify($res3->certificate));

        // Vektor 4: 1 minor USD pada 15650/1 -> 156,5 -> 157; -1 minor -> -157 (menjauhi nol)
        $res4Pos = $calculator->calculateRevaluation(
            foreignBalanceMinor: 1,
            carryingFunctionalIdr: 0,
            rate: new RationalRate(15650, 1),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(157, $res4Pos->revaluedFunctionalIdr);

        $res4Neg = $calculator->calculateRevaluation(
            foreignBalanceMinor: -1,
            carryingFunctionalIdr: 0,
            rate: new RationalRate(15650, 1),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(-157, $res4Neg->revaluedFunctionalIdr);

        // Vektor 5: Pelunasan penuh piutang pertama pada 15700/1:
        // nilai 15.700.000 - carrying asli 15.500.000 -> realized +200.000
        $res5 = $calculator->calculateSettlement(
            settledForeignMinor: 100_000,
            totalForeignMinor: 100_000,
            carryingFunctionalIdr: 15_500_000,
            settlementRate: new RationalRate(15700, 1),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(15_700_000, $res5->settlementValueIdr);
        $this->assertSame(15_500_000, $res5->allocatedCarryingIdr);
        $this->assertSame(200_000, $res5->realizedGainLossIdr);

        // Vektor 6: Pelunasan parsial 40.000 dari 100.000 minor pada 15700/1:
        // carrying proporsional 6.200.000, nilai 6.280.000 -> realized +80.000
        $res6 = $calculator->calculateSettlement(
            settledForeignMinor: 40_000,
            totalForeignMinor: 100_000,
            carryingFunctionalIdr: 15_500_000,
            settlementRate: new RationalRate(15700, 1),
            currencyDecimals: 2,
            isAsset: true
        );
        $this->assertSame(6_280_000, $res6->settlementValueIdr);
        $this->assertSame(6_200_000, $res6->allocatedCarryingIdr);
        $this->assertSame(80_000, $res6->realizedGainLossIdr);

        // Vektor 7: kurs 0/1 -> INVALID_RATE
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('INVALID_RATE');
        new RationalRate(0, 1);
    }

    public function test_corrupted_certificate_throws_invalid_certificate_exception(): void
    {
        $calculator = new FxRevaluationCalculator;
        $res = $calculator->calculateRevaluation(
            foreignBalanceMinor: 100_000,
            carryingFunctionalIdr: 15_500_000,
            rate: new RationalRate(15650, 1),
            currencyDecimals: 2,
            isAsset: true
        );

        $corruptCert = $res->certificate;
        $corruptCert->sha256 = 'corrupted_hash_value_that_fails_verification';

        $this->expectException(InvalidCertificateException::class);
        FxRevaluationCertificateVerifier::assertValid($corruptCert);
    }
}
