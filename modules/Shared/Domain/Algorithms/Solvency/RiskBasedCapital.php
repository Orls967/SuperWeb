<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Solvency;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Risk-Based Capital dengan agregasi korelasi (simulasi S1):
 *   RBC = ⌊√( Σ_i Σ_j ρ_ij · R_i · R_j )⌋ + R_op,   ρ dalam bps (ρ_ii = 10000)
 *   rasio solvabilitas (bps) = ⌊modal_tersedia · 10000 / RBC⌋
 * Aritmetika BigInteger; akar bilangan bulat floor eksak (kuadrat dibuktikan verifier).
 *
 * Kompleksitas: O(K²) untuk K kelas risiko; BigInteger sqrt O(M(n)) pada n digit.
 */
final class RiskBasedCapital
{
    public const int BPS = 10000;

    /**
     * @param  array<string, int>  $riskChargesIdr  kode kelas risiko => muatan risiko (≥ 0)
     * @param  array<string, array<string, int>>  $correlationsBps  korelasi per pasangan; cukup satu arah (a→b atau b→a)
     */
    public function compute(array $riskChargesIdr, array $correlationsBps, int $operationalIdr, int $availableCapitalIdr): SolvencyResult
    {
        if ($riskChargesIdr === []) {
            throw new InvalidArgumentException('Minimal satu kelas risiko diperlukan.');
        }
        if ($operationalIdr < 0 || $availableCapitalIdr < 0) {
            throw new InvalidArgumentException('Muatan operasional dan modal tidak boleh negatif.');
        }

        ksort($riskChargesIdr);
        $codes = array_keys($riskChargesIdr);
        $matrix = $this->buildMatrix($codes, $correlationsBps);

        $sumBps = BigInteger::zero();
        foreach ($codes as $i => $codeI) {
            foreach ($codes as $j => $codeJ) {
                $sumBps = $sumBps->plus(
                    BigInteger::of($matrix[$codeI][$codeJ])->multipliedBy($riskChargesIdr[$codeI])->multipliedBy($riskChargesIdr[$codeJ])
                );
            }
        }

        // floor(√floor(x)) = floor(√x): cukup pembagian bulat sebelum akar.
        $scaledSum = $sumBps->dividedBy(self::BPS, RoundingMode::Down);
        $root = $scaledSum->sqrt(RoundingMode::Down);
        $rbc = $root->plus($operationalIdr);

        if ($rbc->isZero()) {
            throw new InvalidArgumentException('RBC bernilai nol; rasio solvabilitas tidak terdefinisi.');
        }

        $ratio = BigInteger::of($availableCapitalIdr)->multipliedBy(self::BPS)->dividedBy($rbc, RoundingMode::Down);

        return new SolvencyResult(
            riskChargesIdr: $riskChargesIdr,
            correlationMatrixBps: $matrix,
            operationalIdr: $operationalIdr,
            scaledSum: (string) $scaledSum,
            diversifiedIdr: $root->toInt(),
            rbcIdr: $rbc->toInt(),
            availableCapitalIdr: $availableCapitalIdr,
            solvencyRatioBps: $ratio->toInt(),
        );
    }

    /**
     * Modal tambahan minimum agar rasio mencapai $targetRatioBps: ⌈target·RBC/10000⌉ − tersedia.
     */
    public function capitalShortfall(SolvencyResult $result, int $targetRatioBps): int
    {
        $required = BigInteger::of($result->rbcIdr)->multipliedBy($targetRatioBps)->dividedBy(self::BPS, RoundingMode::Up);

        return max($required->toInt() - $result->availableCapitalIdr, 0);
    }

    /**
     * @param  list<string>  $codes
     * @param  array<string, array<string, int>>  $correlationsBps
     * @return array<string, array<string, int>>
     */
    private function buildMatrix(array $codes, array $correlationsBps): array
    {
        $matrix = [];
        foreach ($codes as $a) {
            foreach ($codes as $b) {
                if ($a === $b) {
                    $matrix[$a][$b] = self::BPS;

                    continue;
                }

                $forward = $correlationsBps[$a][$b] ?? null;
                $reverse = $correlationsBps[$b][$a] ?? null;
                if ($forward !== null && $reverse !== null && $forward !== $reverse) {
                    throw new InvalidArgumentException("Korelasi {$a}/{$b} asimetris.");
                }

                $rho = $forward ?? $reverse ?? 0;
                if ($rho < -self::BPS || $rho > self::BPS) {
                    throw new InvalidArgumentException("Korelasi {$a}/{$b} di luar [-10000, 10000] bps.");
                }

                $matrix[$a][$b] = $rho;
            }
        }

        return $matrix;
    }
}
