<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Edi;

use DomainException;
use InvalidArgumentException;
use Modules\Core\Contracts\CanonicalEdiDocument;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Certificate verifier for EdiInterchangeCodec (B3 / W8-D49).
 *
 * Complexity: O(n) independent re-check of SHA-256 digest and segment/acknowledgment invariants.
 */
final class EdiInterchangeCertificateVerifier
{
    /**
     * Verify SHA-256 certificate and structural invariants of decoded EDI document.
     *
     * @throws DomainException On tampered SHA-256 or violated structural invariants.
     */
    public function verify(CanonicalEdiDocument $document): bool
    {
        try {
            $verified = CanonicalCertificateSupport::verifyIntegrity($document->certificate, 'EDI_CERT');
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage(), 0, $e);
        }

        $result = $verified['result'];
        if (($result['is_valid'] ?? null) !== $document->isValid
            || (int) ($result['message_segment_count'] ?? -1) !== $document->messageSegmentCount) {
            throw new DomainException('EDI_CERT_INVARIANT_MISMATCH: Sertifikat dokumen EDI tidak konsisten dengan segmen terurai.');
        }

        if ($document->acknowledgmentPayload === '') {
            throw new DomainException('EDI_CERT_MISSING_ACK: Dokumen EDI wajib memiliki CONTRL atau 997.');
        }

        return true;
    }
}

