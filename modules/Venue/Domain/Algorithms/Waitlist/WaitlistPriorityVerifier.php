<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Waitlist;

final class WaitlistPriorityVerifier
{
    private const SECRET = 'waitlist-governor-v11-secret-key-2026';

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $scores
     */
    public static function sign(array $userIds, array $scores, int $count): string
    {
        $payload = sprintf('%s:%s:%d', implode(',', $userIds), implode(',', $scores), $count);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(WaitlistPriorityCertificate $cert): bool
    {
        $expected = self::sign(
            $cert->rankedUserIds,
            $cert->rankedScores,
            $cert->candidateCount
        );

        return hash_equals($expected, $cert->hmacSignature);
    }
}
