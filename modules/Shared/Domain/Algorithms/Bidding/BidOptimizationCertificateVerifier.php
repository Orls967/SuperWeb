<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Bidding;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

final class BidOptimizationCertificateVerifier
{
    public function verify(BidOptimizationResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['is_valid']) || $cert['is_valid'] !== true) {
            throw new InvalidCertificateException('Certificate marked invalid');
        }

        if (($cert['optimal_price'] ?? null) !== $result->optimalPrice) {
            throw new InvalidCertificateException('Optimal price does not match certificate');
        }

        if (($cert['expected_profit'] ?? null) !== $result->expectedProfitFormatted) {
            throw new InvalidCertificateException('Expected profit does not match certificate');
        }

        $expectedPayload = sprintf(
            '%d|%d|%d|%d|%s|%s',
            $result->c,
            $result->step,
            $result->minWinBps,
            $result->optimalPrice,
            $result->expectedProfitFormatted,
            $result->pWinFormatted
        );

        if (($cert['hash'] ?? null) !== hash('sha256', $expectedPayload)) {
            throw new InvalidCertificateException('Certificate hash mismatch');
        }

        return true;
    }
}
