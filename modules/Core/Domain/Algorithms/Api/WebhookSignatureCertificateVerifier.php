<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Api;

use DomainException;
use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Certificate verifier for WebhookSignatureVerifier (B2 / W8-D48).
 *
 * Complexity: O(V) verification of SHA-256 certificate integrity & status consistency.
 */
final class WebhookSignatureCertificateVerifier
{
    /**
     * Verify certificate digest and state/timestamp invariants.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException On tampered SHA-256 or inconsistent status/window evaluation.
     */
    public function verify(array $payload): bool
    {
        $certificate = $payload['certificate'] ?? null;
        if (! is_array($certificate)) {
            throw new DomainException('WEBHOOK_CERT_MISSING: Sertifikat tanda tangan webhook tidak ditemukan.');
        }

        try {
            $verified = CanonicalCertificateSupport::verifyIntegrity($certificate, 'WEBHOOK_CERT');
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage(), 0, $e);
        }

        $input = $verified['input'];
        $result = $verified['result'];
        $status = (string) ($result['status'] ?? '');

        $allowedStatuses = [
            WebhookSignatureVerifier::STATUS_OK,
            WebhookSignatureVerifier::STATUS_EXPIRED,
            WebhookSignatureVerifier::STATUS_MISMATCH,
            WebhookSignatureVerifier::STATUS_MALFORMED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            throw new DomainException("WEBHOOK_CERT_STATUS_INVALID: Status verifikasi [{$status}] tidak dikenal.");
        }

        if ($status === WebhookSignatureVerifier::STATUS_OK) {
            $delta = $result['delta_seconds'] ?? null;
            $window = (int) ($input['window_seconds'] ?? 300);
            if (! is_int($delta) || $delta > $window || ($result['matched_secret_index'] ?? null) === null) {
                throw new DomainException('WEBHOOK_CERT_INVARIANT_FAILED: Status OK membutuhkan delta <= jendela dan matched_secret_index valid.');
            }
        }

        return true;
    }
}

