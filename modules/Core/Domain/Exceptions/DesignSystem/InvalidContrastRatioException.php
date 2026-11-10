<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions\DesignSystem;

use DomainException;

class InvalidContrastRatioException extends DomainException
{
    public function __construct(int $ratioBps, string $colorA, string $colorB, int $thresholdBps = 45000)
    {
        parent::__construct(
            "Rasio kontras warna {$ratioBps} bps antara '{$colorA}' dan '{$colorB}' di bawah ambang batas WCAG AA ({$thresholdBps} bps)."
        );
    }
}

