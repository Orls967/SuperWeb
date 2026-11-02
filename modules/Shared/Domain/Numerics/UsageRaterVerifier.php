<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for UsageRater calculations and certificates.
 *
 * Enforces PR2 verification:
 * - Every session rated bytes matches upward ceil to 1,024 byte blocks.
 * - Total raw bytes and rated bytes match exact sum of individual sessions.
 * - Excess bytes matches max(0, total_rated_bytes - quota).
 * - Excess MB matches upward ceil of excess_bytes to 1,048,576 bytes.
 * - Total charge identically equals base_charge + excess_charge.
 */
final class UsageRaterVerifier
{
    /**
     * @param array{
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
     *     certificate?: array<string, mixed>
     * } $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(
        array $result,
        int $monthlyQuotaBytes,
        int $basePackageCharge,
        int $excessRatePerMb,
    ): bool {
        if (! isset(
            $result['total_sessions'],
            $result['total_raw_bytes'],
            $result['total_rated_bytes'],
            $result['monthly_quota_bytes'],
            $result['excess_bytes'],
            $result['excess_mb'],
            $result['base_charge'],
            $result['excess_charge'],
            $result['total_charge'],
            $result['rated_sessions']
        )) {
            throw new UnexpectedValueException('Missing required fields in UsageRater result');
        }

        if ($result['monthly_quota_bytes'] !== $monthlyQuotaBytes) {
            throw new UnexpectedValueException("Monthly quota mismatch: expected {$monthlyQuotaBytes}, got {$result['monthly_quota_bytes']}");
        }
        if ($result['base_charge'] !== $basePackageCharge) {
            throw new UnexpectedValueException("Base charge mismatch: expected {$basePackageCharge}, got {$result['base_charge']}");
        }

        $calcRawBytes = 0;
        $calcRatedBytes = 0;

        foreach ($result['rated_sessions'] as $idx => $session) {
            $raw = $session['raw_bytes'];
            $rated = $session['rated_bytes'];

            if ($raw < 0) {
                throw new UnexpectedValueException("Negative raw bytes {$raw} at index {$idx}");
            }

            $expectedRated = ($raw === 0) ? 0 : intdiv($raw + (UsageRater::BYTES_PER_KB - 1), UsageRater::BYTES_PER_KB) * UsageRater::BYTES_PER_KB;
            if ($rated !== $expectedRated) {
                throw new UnexpectedValueException("Rated bytes mismatch at index {$idx}: expected {$expectedRated}, got {$rated}");
            }

            $calcRawBytes += $raw;
            $calcRatedBytes += $rated;
        }

        if (count($result['rated_sessions']) !== $result['total_sessions']) {
            throw new UnexpectedValueException('Total sessions count does not match rated sessions array');
        }
        if ($calcRawBytes !== $result['total_raw_bytes']) {
            throw new UnexpectedValueException("Total raw bytes mismatch: calculated {$calcRawBytes} != claimed {$result['total_raw_bytes']}");
        }
        if ($calcRatedBytes !== $result['total_rated_bytes']) {
            throw new UnexpectedValueException("Total rated bytes mismatch: calculated {$calcRatedBytes} != claimed {$result['total_rated_bytes']}");
        }

        $expectedExcessBytes = max(0, $calcRatedBytes - $monthlyQuotaBytes);
        if ($result['excess_bytes'] !== $expectedExcessBytes) {
            throw new UnexpectedValueException("Excess bytes mismatch: expected {$expectedExcessBytes}, got {$result['excess_bytes']}");
        }

        $expectedExcessMb = ($expectedExcessBytes === 0) ? 0 : intdiv($expectedExcessBytes + (UsageRater::BYTES_PER_MB - 1), UsageRater::BYTES_PER_MB);
        if ($result['excess_mb'] !== $expectedExcessMb) {
            throw new UnexpectedValueException("Excess MB mismatch: expected {$expectedExcessMb}, got {$result['excess_mb']}");
        }

        $expectedExcessCharge = $expectedExcessMb * $excessRatePerMb;
        if ($result['excess_charge'] !== $expectedExcessCharge) {
            throw new UnexpectedValueException("Excess charge mismatch: expected {$expectedExcessCharge}, got {$result['excess_charge']}");
        }

        $expectedTotalCharge = $basePackageCharge + $expectedExcessCharge;
        if ($result['total_charge'] !== $expectedTotalCharge) {
            throw new UnexpectedValueException("Total charge mismatch: expected {$expectedTotalCharge}, got {$result['total_charge']}");
        }

        return true;
    }
}
