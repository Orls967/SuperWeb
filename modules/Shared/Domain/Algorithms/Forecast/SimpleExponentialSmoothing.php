<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Forecast;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Simple Exponential Smoothing (SES) untuk peramalan permintaan dan usulan safety stock (D64-3).
 * s_0 = y_0
 * s_t = alpha * y_t + (1 - alpha) * s_{t-1}
 * Level dikalikan faktor musiman/libur (bps berversi) dan dibulatkan ke atas (ceil) menjadi unit/hari integer.
 */
final class SimpleExponentialSmoothing
{
    private readonly SimpleExponentialSmoothingCertificateVerifier $verifier;

    public function __construct()
    {
        $this->verifier = new SimpleExponentialSmoothingCertificateVerifier;
    }

    /**
     * @param  list<int>  $demands  Deret permintaan historis
     * @param  int  $alphaBps  Koefisien pemulusan alpha dalam bps (misal 3.000 bps = 0,3)
     * @param  int  $holidayFactorBps  Faktor pengali musiman/libur dalam bps (misal 12.500 bps = 1,25)
     */
    public function forecastWithHolidayAdjustment(
        array $demands,
        int $alphaBps = 3000,
        int $holidayFactorBps = 10000
    ): SimpleExponentialSmoothingResult {
        if ($demands === []) {
            throw new InvalidArgumentException('Deret permintaan tidak boleh kosong');
        }

        if ($alphaBps <= 0 || $alphaBps > 10000) {
            throw new InvalidArgumentException("Alpha bps harus di antara 1 dan 10000: {$alphaBps}");
        }

        if ($holidayFactorBps <= 0) {
            throw new InvalidArgumentException("Faktor libur bps harus positif: {$holidayFactorBps}");
        }

        $alpha = BigRational::ofFraction($alphaBps, 10000);
        $oneMinusAlpha = BigRational::one()->minus($alpha);

        $s = BigRational::of($demands[0]);
        $count = count($demands);
        for ($i = 1; $i < $count; $i++) {
            $y = BigRational::of($demands[$i]);
            $s = $alpha->multipliedBy($y)->plus($oneMinusAlpha->multipliedBy($s));
        }

        // Level dibulatkan 2 desimal
        $levelDec = BigDecimal::of($s->getNumerator())->dividedBy(
            BigDecimal::of($s->getDenominator()),
            2,
            RoundingMode::HalfUp
        );
        $levelFormatted = (string) $levelDec;

        // Faktor penyesuaian libur
        $factor = BigRational::ofFraction($holidayFactorBps, 10000);
        $adjusted = $s->multipliedBy($factor);

        $adjDec = BigDecimal::of($adjusted->getNumerator())->dividedBy(
            BigDecimal::of($adjusted->getDenominator()),
            2,
            RoundingMode::HalfUp
        );
        $adjustedLevelFormatted = (string) $adjDec;

        // Pembulatan ke atas (ceil) menjadi integer unit/hari
        $suggestedUnits = BigDecimal::of($adjusted->getNumerator())->dividedBy(
            BigDecimal::of($adjusted->getDenominator()),
            0,
            RoundingMode::Ceiling
        )->toInt();

        $payload = sprintf(
            '%s|%d|%d|%s|%s|%d',
            implode(',', $demands),
            $alphaBps,
            $holidayFactorBps,
            $levelFormatted,
            $adjustedLevelFormatted,
            $suggestedUnits
        );

        $certificate = [
            'is_valid' => true,
            'level_formatted' => $levelFormatted,
            'adjusted_level_formatted' => $adjustedLevelFormatted,
            'suggested_units' => $suggestedUnits,
            'hash' => hash('sha256', $payload),
        ];

        $result = new SimpleExponentialSmoothingResult(
            demands: $demands,
            alphaBps: $alphaBps,
            holidayFactorBps: $holidayFactorBps,
            level: $s,
            levelFormatted: $levelFormatted,
            adjustedLevel: $adjusted,
            adjustedLevelFormatted: $adjustedLevelFormatted,
            suggestedUnitsPerDay: $suggestedUnits,
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }
}

