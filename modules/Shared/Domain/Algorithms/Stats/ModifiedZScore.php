<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Deteksi anomali berbasis Modified Z-Score (D64-2).
 * z = 0.6745 * (x - median) / MAD
 * Bila MAD = 0, z = (x - median) / (1.253314 * MeanAD)
 * Anomali jika |z| > 3.5.
 */
final class ModifiedZScore
{
    private readonly RobustStatistics $robustStats;
    private readonly ModifiedZScoreCertificateVerifier $verifier;

    public function __construct()
    {
        $this->robustStats = new RobustStatistics;
        $this->verifier = new ModifiedZScoreCertificateVerifier;
    }

    /**
     * @param  list<int>  $values
     */
    public function evaluate(int $x, array $values): ModifiedZScoreResult
    {
        if ($values === []) {
            throw new InvalidArgumentException('Kumpulan nilai tidak boleh kosong');
        }

        $median = $this->robustStats->median($values);
        $mad = $this->robustStats->mad($values, $median);

        $diff = BigRational::of($x)->minus($median);
        $meanAd = null;

        if (! $mad->isZero()) {
            // z = (6745 / 10000) * (x - median) / MAD
            $const = BigRational::ofFraction(6745, 10000);
            $z = $const->multipliedBy($diff)->dividedBy($mad);
        } else {
            // Hitung MeanAD = sum(|v_i - median|) / N
            $sumDeviations = BigRational::zero();
            foreach ($values as $val) {
                $dev = BigRational::of($val)->minus($median)->abs();
                $sumDeviations = $sumDeviations->plus($dev);
            }
            $meanAd = $sumDeviations->dividedBy(BigRational::of(count($values)));

            if (! $meanAd->isZero()) {
                // z = (x - median) / ((1253314 / 1000000) * MeanAD)
                $factor = BigRational::ofFraction(1253314, 1000000);
                $denom = $factor->multipliedBy($meanAd);
                $z = $diff->dividedBy($denom);
            } else {
                if ($diff->isZero()) {
                    $z = BigRational::zero();
                } else {
                    $z = $diff->isPositive() ? BigRational::ofFraction(999, 1) : BigRational::ofFraction(-999, 1);
                }
            }
        }

        // Format ke 3 desimal
        $zDec = BigDecimal::of($z->getNumerator())->dividedBy(
            BigDecimal::of($z->getDenominator()),
            3,
            RoundingMode::HalfUp
        );
        $zFormatted = (string) $zDec;

        // Anomali bila |z| > 3.5
        $threshold = BigRational::ofFraction(35, 10);
        $isAnomaly = $z->abs()->isGreaterThan($threshold);

        $payload = sprintf(
            '%d|%s|%s|%s|%s|%d',
            $x,
            $median->toFloat(),
            $mad->toFloat(),
            $zFormatted,
            $isAnomaly ? '1' : '0',
            count($values)
        );

        $certificate = [
            'is_valid' => true,
            'computed_z' => $zFormatted,
            'is_anomaly' => $isAnomaly,
            'hash' => hash('sha256', $payload),
        ];

        $result = new ModifiedZScoreResult(
            x: $x,
            values: $values,
            median: $median,
            mad: $mad,
            meanAd: $meanAd,
            z: $z,
            zFormatted: $zFormatted,
            isAnomaly: $isAnomaly,
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }
}
