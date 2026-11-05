<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

use InvalidArgumentException;

/**
 * Parameter satu peril: frekuensi tahunan (ppm kejadian/tahun untuk seluruh katalog peril ini),
 * rentang intensitas (bps skala 0–10000) dan kurva vulnerability piecewise-linear
 * (titik intensitas_bps → damage_bps, menaik).
 */
final readonly class CatPerilParams
{
    /**
     * @param  list<array{intensity_bps: int, damage_bps: int}>  $vulnerability
     */
    public function __construct(
        public string $code,
        public int $annualFrequencyPpm,
        public int $minIntensityBps,
        public int $maxIntensityBps,
        public array $vulnerability,
    ) {
        if ($code === '' || $annualFrequencyPpm <= 0 || $minIntensityBps < 0 || $maxIntensityBps > 10000 || $minIntensityBps > $maxIntensityBps) {
            throw new InvalidArgumentException("Parameter peril '{$code}' tidak valid.");
        }
        if (count($vulnerability) < 2) {
            throw new InvalidArgumentException('Kurva vulnerability minimal dua titik.');
        }

        $previousX = -1;
        $previousY = -1;
        foreach ($vulnerability as $point) {
            if ($point['intensity_bps'] <= $previousX || $point['damage_bps'] < $previousY || $point['damage_bps'] > 10000) {
                throw new InvalidArgumentException('Kurva vulnerability harus menaik dan damage ≤ 10000 bps.');
            }
            $previousX = $point['intensity_bps'];
            $previousY = $point['damage_bps'];
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'annual_frequency_ppm' => $this->annualFrequencyPpm,
            'min_intensity_bps' => $this->minIntensityBps,
            'max_intensity_bps' => $this->maxIntensityBps,
            'vulnerability' => $this->vulnerability,
        ];
    }
}
