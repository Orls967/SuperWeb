<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class SponsorExposureVerifier
{
    private const SECRET = 'venue-sponsor-exposure-valuator-secret-v1';

    public static function sign(
        int $delivered,
        int $target,
        int $achievement,
        int $payoutRatio,
        int $exposureVal,
        int $milestonePayout
    ): string {
        $payload = implode(':', [
            $delivered,
            $target,
            $achievement,
            $payoutRatio,
            $exposureVal,
            $milestonePayout,
        ]);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(SponsorExposureCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->deliveredImpressions,
            $certificate->targetImpressions,
            $certificate->achievementBps,
            $certificate->payoutRatioBps,
            $certificate->exposureValueIdr,
            $certificate->milestonePayoutIdr
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('SponsorExposureCertificate HMAC signature is invalid or tampered.');
        }

        // Validasi formula pencapaian KPI
        if ($certificate->targetImpressions > 0) {
            $expectedAchieve = (int) intdiv($certificate->deliveredImpressions * 10_000, $certificate->targetImpressions);
            if ($certificate->achievementBps !== $expectedAchieve) {
                throw new InvalidArgumentException('Achievement basis points mismatch.');
            }
        }

        // Validasi batas kelayakan payout
        if ($certificate->achievementBps >= 10_000) {
            if ($certificate->payoutRatioBps !== 10_000) {
                throw new InvalidArgumentException('Full payout ratio required when achievement >= 100%.');
            }
        } elseif ($certificate->achievementBps < 7_000) {
            if ($certificate->payoutRatioBps !== 0) {
                throw new InvalidArgumentException('Payout must be 0 when achievement < 70%.');
            }
        }

        return true;
    }
}
