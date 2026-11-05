<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Solvency;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifier RBC (PR2): s² ≤ Σ < (s+1)², matriks simetris & terbatas, dan rasio = ⌊modal·10⁴ / RBC⌋.
 */
final class RiskBasedCapitalCertificateVerifier
{
    public function verify(SolvencyResult $result): true
    {
        foreach ($result->correlationMatrixBps as $a => $row) {
            foreach ($row as $b => $rho) {
                if ($rho < -10000 || $rho > 10000) {
                    throw new InvalidCertificateException("ρ({$a},{$b}) di luar batas");
                }
                if ($rho !== $result->correlationMatrixBps[$b][$a]) {
                    throw new InvalidCertificateException("Matriks korelasi tidak simetris pada ({$a},{$b})");
                }
                if ($a === $b && $rho !== 10000) {
                    throw new InvalidCertificateException("ρ diagonal {$a} harus 10000");
                }
            }
        }

        $sum = BigInteger::zero();
        foreach ($result->riskChargesIdr as $a => $ra) {
            foreach ($result->riskChargesIdr as $b => $rb) {
                $sum = $sum->plus(BigInteger::of($result->correlationMatrixBps[$a][$b])->multipliedBy($ra)->multipliedBy($rb));
            }
        }

        $scaled = $sum->dividedBy(10000, RoundingMode::Down);
        if ((string) $scaled !== $result->scaledSum) {
            throw new InvalidCertificateException('Jumlah terskala tidak cocok dengan matriks');
        }

        $root = BigInteger::of($result->diversifiedIdr);
        if ($root->power(2)->isGreaterThan($scaled) || $root->plus(1)->power(2)->isLessThanOrEqualTo($scaled)) {
            throw new InvalidCertificateException('Akar bulat bukan floor eksak (s² ≤ Σ < (s+1)²)');
        }

        if ($result->rbcIdr !== $result->diversifiedIdr + $result->operationalIdr) {
            throw new InvalidCertificateException('RBC ≠ akar + operasional');
        }

        $ratio = BigInteger::of($result->solvencyRatioBps);
        $capital = BigInteger::of($result->availableCapitalIdr)->multipliedBy(10000);
        $rbc = BigInteger::of($result->rbcIdr);
        if ($ratio->multipliedBy($rbc)->isGreaterThan($capital) || $ratio->plus(1)->multipliedBy($rbc)->isLessThanOrEqualTo($capital)) {
            throw new InvalidCertificateException('Rasio solvabilitas bukan floor eksak');
        }

        return true;
    }
}
