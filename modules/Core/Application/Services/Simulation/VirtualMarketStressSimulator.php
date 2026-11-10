<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;

/**
 * Simulator stres pasar dan guncangan makroekonomi terisolasi.
 * Menghasilkan kurva deviasi deterministik untuk suku bunga, inflasi, dan indeks volatilitas.
 */
final class VirtualMarketStressSimulator
{
    public function __construct(
        private readonly StreamSeedAndFingerprint $streamRng,
    ) {}

    /**
     * @return array{
     *     day: int,
     *     base_rate_pct: float,
     *     inflation_rate_pct: float,
     *     fx_rate_usd_idr: float,
     *     volatility_index: float,
     *     stress_factor: float
     * }
     */
    public function simulateMacroConditions(int $baseSeed, int $dayNumber, string $shockProfile = 'standard'): array
    {
        if ($dayNumber < 1) {
            throw new InvalidArgumentException("Hari simulasi harus >= 1: {$dayNumber}");
        }

        $streamSeed = $this->streamRng->split($baseSeed, 'market_stress');
        $stepSeed = ($streamSeed + ($dayNumber * 997)) % 2147483647;

        // Faktor guncangan berbasis profil
        $multiplier = match ($shockProfile) {
            'severe_shock' => 2.5,
            'moderate_volatility' => 1.5,
            'mild_stress' => 1.2,
            default => 1.0,
        };

        $pseudoNorm = (($stepSeed % 1000) / 1000.0) - 0.5; // -0.5 .. +0.5

        $baseRate = 6.00 + ($pseudoNorm * 1.5 * $multiplier);
        $inflation = 3.50 + ($pseudoNorm * 2.0 * $multiplier);
        $fxRate = 16000.0 + ($pseudoNorm * 1200.0 * $multiplier);
        $vix = 18.0 + (abs($pseudoNorm) * 20.0 * $multiplier);

        return [
            'day' => $dayNumber,
            'base_rate_pct' => round(max(0.1, $baseRate), 4),
            'inflation_rate_pct' => round(max(0.0, $inflation), 4),
            'fx_rate_usd_idr' => round(max(10000.0, $fxRate), 2),
            'volatility_index' => round(max(5.0, $vix), 2),
            'stress_factor' => round($multiplier, 2),
        ];
    }
}
