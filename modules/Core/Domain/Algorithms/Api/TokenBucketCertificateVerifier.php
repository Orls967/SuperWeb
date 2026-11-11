<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Api;

use DomainException;
use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Certificate verifier for IntegerTokenBucket (B1 / W8-D47).
 *
 * Complexity: O(1) independent recomputation of integer refill & deficit arithmetic.
 */
final class TokenBucketCertificateVerifier
{
    /**
     * Verify SHA-256 certificate digest and independent token-bucket invariants.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException On tampered SHA-256 or violated invariant.
     */
    public function verify(array $payload): bool
    {
        $certificate = $payload['certificate'] ?? null;
        if (! is_array($certificate)) {
            throw new DomainException('BUCKET_CERT_MISSING: Sertifikat bucket tidak ditemukan.');
        }

        try {
            $verified = CanonicalCertificateSupport::verifyIntegrity($certificate, 'BUCKET_CERT');
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage(), 0, $e);
        }

        $input = $verified['input'];
        $certResult = $verified['result'];

        $capacity = (int) ($input['capacity'] ?? 0);
        $ratePerSec = (int) ($input['rate_per_sec'] ?? 0);
        $currentMilli = (int) ($input['current_milli_tokens'] ?? -1);
        $lastMs = (int) ($input['last_ms'] ?? 0);
        $nowMs = (int) ($input['now_ms'] ?? 0);
        $costTokens = (int) ($input['cost_tokens'] ?? 1);

        if ($capacity < 1 || $ratePerSec < 1 || $nowMs < $lastMs) {
            throw new DomainException('BUCKET_CERT_INPUT_INVALID: Parameter masukan sertifikat bucket tidak sah.');
        }

        $maxMilli = $capacity * IntegerTokenBucket::MILLI_PER_TOKEN;
        $expectedRefilled = min($maxMilli, max(0, min($maxMilli, $currentMilli)) + (($nowMs - $lastMs) * $ratePerSec));
        $costMilli = $costTokens * IntegerTokenBucket::MILLI_PER_TOKEN;
        $expectedAllowed = $expectedRefilled >= $costMilli;
        $expectedRemaining = $expectedAllowed ? ($expectedRefilled - $costMilli) : $expectedRefilled;
        $expectedRetryMs = $expectedAllowed ? 0 : intdiv(($costMilli - $expectedRefilled) + $ratePerSec - 1, $ratePerSec);

        if (($certResult['allowed'] ?? null) !== $expectedAllowed
            || (int) ($certResult['remaining_milli_tokens'] ?? -1) !== $expectedRemaining
            || (int) ($certResult['retry_after_ms'] ?? -1) !== $expectedRetryMs) {
            throw new DomainException('BUCKET_CERT_INVARIANT_MISMATCH: Hasil sertifikat token bucket menyimpang dari kalkulasi independen.');
        }

        return true;
    }
}
