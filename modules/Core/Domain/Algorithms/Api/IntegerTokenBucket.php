<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Api;

use DomainException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Deterministic Integer Milli-Token Bucket rate limiter (B1 / W8-D47).
 *
 * 1 full token = 1,000 integer milli-tokens. No floating-point arithmetic is used
 * anywhere so refills and wait times are bit-exact across PHP/DB environments.
 *
 * Complexity: O(1) time and space per consumption call. Bounded integer arithmetic.
 * Parameter source: asumsi_simulasi (Silver/Gold/Platinum tiers per ADR-0047).
 */
final class IntegerTokenBucket
{
    public const MILLI_PER_TOKEN = 1000;

    /**
     * Evaluate and apply one request consumption against milli-token bucket state.
     *
     * @return array{
     *     allowed: bool,
     *     remaining_tokens: int,
     *     remaining_milli_tokens: int,
     *     refilled_milli_tokens: int,
     *     last_ms: int,
     *     retry_after_ms: int,
     *     retry_after_seconds: int,
     *     certificate: array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     * }
     *
     * @throws DomainException On negative elapsed clock regression or invalid parameters.
     */
    public function consume(
        int $currentMilliTokens,
        int $lastMs,
        int $nowMs,
        int $capacity,
        int $ratePerSec,
        int $costTokens = 1,
    ): array {
        if ($capacity < 1 || $ratePerSec < 1 || $costTokens < 1) {
            throw new DomainException('BUCKET_PARAM_INVALID: Kapasitas, laju, dan biaya token wajib bernilai >= 1.');
        }

        if ($nowMs < $lastMs) {
            throw new DomainException("CLOCK_REGRESSION: Waktu permintaan ({$nowMs} ms) mendahului stempel terakhir ({$lastMs} ms).");
        }

        $maxMilli = $capacity * self::MILLI_PER_TOKEN;
        $clampedCurrent = max(0, min($maxMilli, $currentMilliTokens));
        $elapsedMs = $nowMs - $lastMs;

        // 1 token/s = 1,000 milli-tokens / 1,000 ms = 1 milli-token per ms -> exact integer!
        $addedMilli = $elapsedMs * $ratePerSec;
        $refilledMilli = min($maxMilli, $clampedCurrent + $addedMilli);
        $costMilli = $costTokens * self::MILLI_PER_TOKEN;

        $steps = [
            "clamp(current={$currentMilliTokens}, max={$maxMilli}) => {$clampedCurrent}",
            "refill(elapsed_ms={$elapsedMs}, rate={$ratePerSec}, added={$addedMilli}) => {$refilledMilli}",
        ];

        if ($refilledMilli >= $costMilli) {
            $remainingMilli = $refilledMilli - $costMilli;
            $allowed = true;
            $retryAfterMs = 0;
            $retryAfterSec = 0;
            $steps[] = "consume(cost={$costMilli}) => ALLOW remaining_milli={$remainingMilli}";
        } else {
            $remainingMilli = $refilledMilli;
            $allowed = false;
            $deficitMilli = $costMilli - $refilledMilli;
            $retryAfterMs = intdiv($deficitMilli + $ratePerSec - 1, $ratePerSec);
            $retryAfterSec = intdiv($retryAfterMs + 999, 1000);
            $steps[] = "reject(deficit={$deficitMilli}) => DENY retry_after_ms={$retryAfterMs}";
        }

        $remainingTokens = intdiv($remainingMilli, self::MILLI_PER_TOKEN);

        $input = [
            'current_milli_tokens' => $currentMilliTokens,
            'last_ms' => $lastMs,
            'now_ms' => $nowMs,
            'capacity' => $capacity,
            'rate_per_sec' => $ratePerSec,
            'cost_tokens' => $costTokens,
        ];

        $result = [
            'allowed' => $allowed,
            'remaining_tokens' => $remainingTokens,
            'remaining_milli_tokens' => $remainingMilli,
            'refilled_milli_tokens' => $refilledMilli,
            'last_ms' => $nowMs,
            'retry_after_ms' => $retryAfterMs,
            'retry_after_seconds' => $retryAfterSec,
        ];

        $certificate = CanonicalCertificateSupport::issue($input, $steps, $result);

        return [...$result, 'certificate' => $certificate];
    }
}
