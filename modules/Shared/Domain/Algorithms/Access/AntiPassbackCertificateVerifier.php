<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Access;

use InvalidArgumentException;

final class AntiPassbackCertificateVerifier
{
    private const SECRET = 'venue-anti-passback-secret-v1';

    /**
     * @param list<array{direction: string, nonce: string, timestamp: string, hash: string}> $history
     */
    public static function sign(string $ticketId, int $maxReentry, int $reentryCount, string $direction, int $net, array $history): string
    {
        $payload = "{$ticketId}:{$maxReentry}:{$reentryCount}:{$direction}:{$net}:".json_encode($history);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(AntiPassbackCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->ticketId,
            $certificate->maxReentry,
            $certificate->reentryCount,
            $certificate->currentDirection,
            $certificate->netInMinusOut,
            $certificate->history
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('AntiPassbackCertificate HMAC signature is invalid');
        }

        if ($certificate->netInMinusOut !== 0 && $certificate->netInMinusOut !== 1) {
            throw new InvalidArgumentException('AntiPassbackCertificate invariant violated: netInMinusOut must be 0 or 1');
        }

        if ($certificate->reentryCount > $certificate->maxReentry) {
            throw new InvalidArgumentException('AntiPassbackCertificate invariant violated: reentryCount exceeds maxReentry');
        }

        return true;
    }
}

