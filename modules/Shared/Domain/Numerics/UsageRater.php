<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Telecom Data Usage Rater.
 *
 * Rates raw data session CDRs with:
 * - Upward ceiling per session to 1 KB (1,024 bytes) blocks.
 * - Monthly quota deduction from total rated bytes.
 * - Monthly excess billed per full MB (1,048,576 bytes) ceiling.
 * - Exact IDR billing calculation.
 *
 * Complexity: O(N) where N is the number of session CDRs.
 */
final class UsageRater
{
    public const int BYTES_PER_KB = 1024;

    public const int BYTES_PER_MB = 1048576; // 1024 * 1024

    /**
     * @param  list<int|array{session_id?: string|int, bytes: int}>  $sessions  Raw CDR session byte counts
     * @param  int  $monthlyQuotaBytes  Monthly inclusive quota in bytes (>= 0)
     * @param  int  $basePackageCharge  Monthly base subscription fee in IDR (>= 0)
     * @param  int  $excessRatePerMb  Overage charge per MB in IDR (>= 0)
     * @return array{
     *     total_sessions: int,
     *     total_raw_bytes: int,
     *     total_rated_bytes: int,
     *     monthly_quota_bytes: int,
     *     excess_bytes: int,
     *     excess_mb: int,
     *     base_charge: int,
     *     excess_charge: int,
     *     total_charge: int,
     *     rated_sessions: list<array{
     *         session_id: string|int,
     *         raw_bytes: int,
     *         rated_bytes: int
     *     }>,
     *     certificate: array{
     *         total_sessions: int,
     *         total_raw_bytes: int,
     *         total_rated_bytes: int,
     *         monthly_quota_bytes: int,
     *         excess_bytes: int,
     *         excess_mb: int,
     *         base_charge: int,
     *         excess_charge: int,
     *         total_charge: int,
     *         rated_sessions: list<array{
     *             session_id: string|int,
     *             raw_bytes: int,
     *             rated_bytes: int
     *         }>
     *     }
     * }
     */
    public static function rate(
        array $sessions,
        int $monthlyQuotaBytes,
        int $basePackageCharge,
        int $excessRatePerMb,
    ): array {
        if ($monthlyQuotaBytes < 0) {
            throw new InvalidArgumentException("Monthly quota cannot be negative, got: {$monthlyQuotaBytes}");
        }
        if ($basePackageCharge < 0) {
            throw new InvalidArgumentException("Base charge cannot be negative, got: {$basePackageCharge}");
        }
        if ($excessRatePerMb < 0) {
            throw new InvalidArgumentException("Excess rate per MB cannot be negative, got: {$excessRatePerMb}");
        }

        $totalRawBytes = 0;
        $totalRatedBytes = 0;
        $ratedSessions = [];

        foreach ($sessions as $idx => $session) {
            if (is_int($session)) {
                $rawBytes = $session;
                $sessionId = 'sess-'.$idx;
            } elseif (is_array($session) && isset($session['bytes'])) {
                $rawBytes = $session['bytes'];
                $sessionId = $session['session_id'] ?? ('sess-'.$idx);
            } else {
                throw new InvalidArgumentException("Invalid session format at index {$idx}");
            }

            if ($rawBytes < 0) {
                throw new InvalidArgumentException("Session bytes cannot be negative at index {$idx}, got: {$rawBytes}");
            }

            // Ceil to 1 KB (1024 bytes) blocks
            $ratedBytes = ($rawBytes === 0) ? 0 : intdiv($rawBytes + (self::BYTES_PER_KB - 1), self::BYTES_PER_KB) * self::BYTES_PER_KB;

            $totalRawBytes += $rawBytes;
            $totalRatedBytes += $ratedBytes;

            $ratedSessions[] = [
                'session_id' => $sessionId,
                'raw_bytes' => $rawBytes,
                'rated_bytes' => $ratedBytes,
            ];
        }

        $excessBytes = max(0, $totalRatedBytes - $monthlyQuotaBytes);
        $excessMb = ($excessBytes === 0) ? 0 : intdiv($excessBytes + (self::BYTES_PER_MB - 1), self::BYTES_PER_MB);

        $excessCharge = $excessMb * $excessRatePerMb;
        $totalCharge = $basePackageCharge + $excessCharge;

        $certificate = [
            'total_sessions' => count($sessions),
            'total_raw_bytes' => $totalRawBytes,
            'total_rated_bytes' => $totalRatedBytes,
            'monthly_quota_bytes' => $monthlyQuotaBytes,
            'excess_bytes' => $excessBytes,
            'excess_mb' => $excessMb,
            'base_charge' => $basePackageCharge,
            'excess_charge' => $excessCharge,
            'total_charge' => $totalCharge,
            'rated_sessions' => $ratedSessions,
        ];

        $result = [
            'total_sessions' => count($sessions),
            'total_raw_bytes' => $totalRawBytes,
            'total_rated_bytes' => $totalRatedBytes,
            'monthly_quota_bytes' => $monthlyQuotaBytes,
            'excess_bytes' => $excessBytes,
            'excess_mb' => $excessMb,
            'base_charge' => $basePackageCharge,
            'excess_charge' => $excessCharge,
            'total_charge' => $totalCharge,
            'rated_sessions' => $ratedSessions,
            'certificate' => $certificate,
        ];

        UsageRaterVerifier::verify($result, $monthlyQuotaBytes, $basePackageCharge, $excessRatePerMb);

        return $result;
    }
}
