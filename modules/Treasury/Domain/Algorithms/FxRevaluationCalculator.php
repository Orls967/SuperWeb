<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms;

use Modules\Treasury\Domain\Algorithms\Certificates\FxRevaluationCertificate;
use Modules\Treasury\Domain\DTOs\RevaluationResultDTO;
use Modules\Treasury\Domain\DTOs\SettlementResultDTO;

/**
 * Algoritma D1: Kalkulator Revaluasi Pos Moneter Valas Sesuai PSAK 10.
 *
 * Kompleksitas Waktu: O(1)
 * Kompleksitas Ruang: O(1)
 * Pembulatan: Setengah menjauhi nol (round half away from zero) pada level baris.
 * Aritmetika: Bilangan bulat murni tanpa float.
 */
final class FxRevaluationCalculator
{
    public function calculateRevaluation(
        int $foreignBalanceMinor,
        int $carryingFunctionalIdr,
        RationalRate $rate,
        int $currencyDecimals = 2,
        bool $isAsset = true
    ): RevaluationResultDTO {
        $scale = 10 ** $currencyDecimals;
        $numerator = $foreignBalanceMinor * $rate->numerator;
        $denominator = $rate->denominator * $scale;

        $revaluedFunctionalIdr = RationalRate::roundHalfAwayFromZero($numerator, $denominator);

        $difference = $revaluedFunctionalIdr - $carryingFunctionalIdr;

        // Aset: revalued > carrying -> gain (+)
        // Liabilitas: revalued > carrying -> loss (-)
        $unrealizedGainLossIdr = $isAsset ? $difference : -$difference;

        $canonicalInputs = [
            'currency_decimals' => $currencyDecimals,
            'current_carrying_idr' => $carryingFunctionalIdr,
            'foreign_balance_minor' => $foreignBalanceMinor,
            'is_asset' => $isAsset,
            'rate_denominator' => $rate->denominator,
            'rate_numerator' => $rate->numerator,
        ];

        $steps = [
            'scale' => $scale,
            'numerator' => $numerator,
            'denominator' => $denominator,
            'revalued_idr' => $revaluedFunctionalIdr,
            'diff' => $difference,
        ];

        $results = [
            'is_gain' => $unrealizedGainLossIdr > 0,
            'is_loss' => $unrealizedGainLossIdr < 0,
            'revalued_functional_idr' => $revaluedFunctionalIdr,
            'unrealized_gain_loss_idr' => $unrealizedGainLossIdr,
        ];

        $certificate = FxRevaluationCertificate::create($canonicalInputs, $steps, $results);

        return new RevaluationResultDTO(
            revaluedFunctionalIdr: $revaluedFunctionalIdr,
            unrealizedGainLossIdr: $unrealizedGainLossIdr,
            isAsset: $isAsset,
            certificate: $certificate
        );
    }

    public function calculateSettlement(
        int $settledForeignMinor,
        int $totalForeignMinor,
        int $carryingFunctionalIdr,
        RationalRate $settlementRate,
        int $currencyDecimals = 2,
        bool $isAsset = true
    ): SettlementResultDTO {
        $scale = 10 ** $currencyDecimals;

        $allocatedCarryingIdr = $totalForeignMinor > 0
            ? RationalRate::roundHalfAwayFromZero($settledForeignMinor * $carryingFunctionalIdr, $totalForeignMinor)
            : 0;

        $settlementValueIdr = RationalRate::roundHalfAwayFromZero(
            $settledForeignMinor * $settlementRate->numerator,
            $settlementRate->denominator * $scale
        );

        $realizedGainLossIdr = $isAsset
            ? ($settlementValueIdr - $allocatedCarryingIdr)
            : ($allocatedCarryingIdr - $settlementValueIdr);

        return new SettlementResultDTO(
            settlementValueIdr: $settlementValueIdr,
            allocatedCarryingIdr: $allocatedCarryingIdr,
            realizedGainLossIdr: $realizedGainLossIdr,
            isAsset: $isAsset
        );
    }

    public function revalueSingle(
        string $currency,
        int $foreignBalance,
        int $bookFunctional,
        ?RationalRate $rate = null,
        string $period = '',
        ?RationalRate $closingRate = null,
        int $currencyDecimals = 2,
        bool $isAsset = true
    ): FxRevaluationCertificate {
        $activeRate = $rate ?? $closingRate;
        if ($activeRate === null) {
            throw new \InvalidArgumentException('Rate must be provided');
        }

        $res = $this->calculateRevaluation(
            foreignBalanceMinor: $foreignBalance,
            carryingFunctionalIdr: $bookFunctional,
            rate: $activeRate,
            currencyDecimals: $currencyDecimals,
            isAsset: $isAsset
        );

        return $res->certificate;
    }
}
