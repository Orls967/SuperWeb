<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class MembershipTierVerifier
{
    private const SECRET = 'venue-membership-tier-engine-secret-v1';

    public static function sign(
        string $currentTier,
        int $spend365,
        int $visits365,
        bool $isAnniversary,
        string $resolvedTier,
        bool $inGrace,
        bool $eligibleLocker
    ): string {
        $payload = implode(':', [
            $currentTier,
            $spend365,
            $visits365,
            $isAnniversary ? '1' : '0',
            $resolvedTier,
            $inGrace ? '1' : '0',
            $eligibleLocker ? '1' : '0',
        ]);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(MembershipTierCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->currentTier,
            $certificate->spend365DaysIdr,
            $certificate->visits365Days,
            $certificate->isAnniversaryEvaluation,
            $certificate->resolvedTier,
            $certificate->inGracePeriod,
            $certificate->eligibleForBottleLocker
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('MembershipTierCertificate HMAC signature is invalid or tampered.');
        }

        // Validasi Aturan Bisnis
        if ($certificate->resolvedTier === 'infinity' && ! $certificate->eligibleForBottleLocker) {
            throw new InvalidArgumentException('Infinity tier members must be eligible for bottle locker storage.');
        }

        return true;
    }
}
