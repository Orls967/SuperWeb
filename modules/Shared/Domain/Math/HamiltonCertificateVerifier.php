<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class HamiltonCertificateVerifier
{
    /**
     * Verifies the Hamilton apportionment certificate according to quota and conservation rules.
     * Throws InvalidCertificateException if any invariant is violated.
     *
     * Complexity: O(N) time, O(1) auxiliary space.
     *
     * @throws InvalidCertificateException
     */
    public static function verify(HamiltonCertificate $certificate): void
    {
        $sumAllocated = 0;
        foreach ($certificate->allocations as $amount) {
            if ($amount < 0) {
                throw new InvalidCertificateException("Negative allocation detected: {$amount}");
            }
            $sumAllocated += $amount;
        }

        if ($sumAllocated + $certificate->unallocated !== $certificate->total) {
            throw new InvalidCertificateException(
                "Conservation invariant violated: sum of allocations ({$sumAllocated}) + unallocated ({$certificate->unallocated}) != total ({$certificate->total})"
            );
        }

        $allHaveCaps = true;
        $sumCaps = 0;
        $caps = [];
        foreach ($certificate->items as $item) {
            if (! isset($item['cap'])) {
                $allHaveCaps = false;
            } else {
                $caps[$item['key']] = $item['cap'];
                $sumCaps += $item['cap'];
            }
        }

        if ($allHaveCaps && ! empty($certificate->items) && $certificate->total >= $sumCaps) {
            foreach ($certificate->allocations as $key => $amount) {
                $cap = $caps[$key];
                if ($amount !== $cap) {
                    throw new InvalidCertificateException("Allocation for {$key} ({$amount}) does not equal its cap ({$cap}) under full-cap saturation");
                }
            }
            $expectedUnallocated = $certificate->total - $sumCaps;
            if ($certificate->unallocated !== $expectedUnallocated) {
                throw new InvalidCertificateException(
                    "Unallocated amount ({$certificate->unallocated}) does not match expected ({$expectedUnallocated})"
                );
            }

            return;
        }

        if ($certificate->unallocated !== 0) {
            throw new InvalidCertificateException("Non-zero unallocated remainder ({$certificate->unallocated}) in standard apportionment");
        }

        // Quota rule: floor(quota) <= allocation <= ceil(quota), and allocation <= cap
        foreach ($certificate->items as $item) {
            $key = $item['key'];
            if (! array_key_exists($key, $certificate->allocations)) {
                throw new InvalidCertificateException("Missing allocation for key: {$key}");
            }

            $allocated = $certificate->allocations[$key];
            if (isset($item['cap']) && $allocated > $item['cap']) {
                throw new InvalidCertificateException("Allocation for {$key} ({$allocated}) exceeds cap ({$item['cap']})");
            }

            if (isset($certificate->quotas[$key])) {
                $quota = $certificate->quotas[$key];
                $floor = $quota->getNumerator()->dividedBy($quota->getDenominator(), RoundingMode::Floor)->toInt();
                $ceil = $quota->getNumerator()->dividedBy($quota->getDenominator(), RoundingMode::Ceiling)->toInt();

                if ($allocated < $floor || $allocated > $ceil) {
                    throw new InvalidCertificateException(
                        "Quota rule violated for key {$key}: allocation {$allocated} outside [{$floor}, {$ceil}]"
                    );
                }
            }
        }
    }
}
