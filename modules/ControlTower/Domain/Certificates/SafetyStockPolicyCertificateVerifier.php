<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final class SafetyStockPolicyCertificateVerifier
{
    public function verify(SafetyStockPolicyCertificate $cert): bool
    {
        if (! in_array($cert->abcClass, ['A', 'B', 'C'], true) || ! in_array($cert->xyzClass, ['X', 'Y', 'Z'], true)) {
            return false;
        }

        if ($cert->safetyStockQty < 0 || $cert->reorderPointQty < $cert->safetyStockQty) {
            return false;
        }

        $expected = hash('sha256', implode('|', [
            $cert->itemCode,
            $cert->abcClass,
            $cert->xyzClass,
            (string) $cert->serviceLevelBps,
            (string) $cert->safetyStockQty,
            (string) $cert->reorderPointQty,
        ]));

        return hash_equals($expected, $cert->digest);
    }
}
