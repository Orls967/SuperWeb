<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Aturan klasifikasi ABC & interval cycle count dalam basis points dan hari.
 */
final readonly class AbcClassificationRule
{
    public function __construct(
        public int $classAThresholdBps = 8000,
        public int $classBThresholdBps = 9500,
        public int $classAIntervalDays = 30,
        public int $classBIntervalDays = 90,
        public int $classCIntervalDays = 360,
    ) {
        if ($classAThresholdBps <= 0 || $classBThresholdBps <= $classAThresholdBps || $classBThresholdBps > 10000) {
            throw new InvalidArgumentException('Ambang batas ABC tidak valid.');
        }
        if ($classAIntervalDays <= 0 || $classBIntervalDays <= 0 || $classCIntervalDays <= 0) {
            throw new InvalidArgumentException('Interval hari cycle count harus > 0.');
        }
    }

    public function intervalForClass(string $abcClass): int
    {
        return match (strtoupper(trim($abcClass))) {
            'A' => $this->classAIntervalDays,
            'B' => $this->classBIntervalDays,
            'C' => $this->classCIntervalDays,
            default => throw new InvalidArgumentException("Kelas ABC tidak valid: {$abcClass}"),
        };
    }

    public function classifyByCumulativeBps(int $cumulativeBps): string
    {
        if ($cumulativeBps <= $this->classAThresholdBps) {
            return 'A';
        }
        if ($cumulativeBps <= $this->classBThresholdBps) {
            return 'B';
        }

        return 'C';
    }
}
