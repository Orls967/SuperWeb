<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use DomainException;

/**
 * Verifikator independen untuk PickSequenceCertificate (H2).
 */
final class PickSequenceCertificateVerifier
{
    public function verify(PickSequenceCertificate $certificate): bool
    {
        if (! in_array($certificate->strategy, ['S_SHAPE', 'LARGEST_GAP'], true)) {
            throw new DomainException("Strategi picking tidak valid: {$certificate->strategy}.");
        }

        if (count($certificate->sequence) !== $certificate->inputPickCount) {
            throw new DomainException('Jumlah pick pada sequence tidak sesuai dengan input.');
        }

        $expectedMin = min($certificate->sShapeDistance, $certificate->largestGapDistance);
        if ($certificate->totalDistance !== $expectedMin) {
            throw new DomainException('Total jarak lintasan tidak memilih strategi minimum.');
        }

        if ($certificate->sShapeDistance === $certificate->largestGapDistance && $certificate->strategy !== 'S_SHAPE') {
            throw new DomainException('Tie-breaking wajib memilih S_SHAPE.');
        }

        return true;
    }
}
