<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Independent verifier for WBS progress certificates (PR2 Verification).
 */
final class WeightedTreeCertificateVerifier
{
    public static function verify(WeightedTreeCertificate $cert, int $maxAllowedDiscrepancyBps = 10): void
    {
        if ($cert->bottomUpProgressBps < 0 || $cert->bottomUpProgressBps > 10_000) {
            throw new InvalidCertificateException("Bottom-up progress out of valid range: {$cert->bottomUpProgressBps}");
        }
        if ($cert->topDownProgressBps < 0 || $cert->topDownProgressBps > 10_000) {
            throw new InvalidCertificateException("Top-down progress out of valid range: {$cert->topDownProgressBps}");
        }

        $diff = abs($cert->bottomUpProgressBps - $cert->topDownProgressBps);
        if ($diff !== $cert->discrepancyBps || $diff > $maxAllowedDiscrepancyBps) {
            throw new InvalidCertificateException("WBS verification failed: bottom-up {$cert->bottomUpProgressBps} != top-down {$cert->topDownProgressBps} (discrepancy {$diff} > max allowed {$maxAllowedDiscrepancyBps})");
        }
    }
}
