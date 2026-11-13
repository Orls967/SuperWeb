<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use DomainException;

/**
 * Verifikator independen untuk PutawaySuggestionCertificate (H1).
 */
final class PutawaySuggestionCertificateVerifier
{
    public function verify(PutawaySuggestionCertificate $certificate): bool
    {
        if ($certificate->requestedQty <= 0) {
            throw new DomainException('Sertifikat putaway tidak valid: requestedQty <= 0.');
        }

        $binMap = [];
        foreach ($certificate->inputBins as $bin) {
            $binMap[$bin['id']] = $bin['freeCapacity'];
        }

        $totalAllocated = 0;
        $seenBins = [];

        foreach ($certificate->allocations as $alloc) {
            $binId = $alloc['bin_id'];
            $qty = $alloc['qty'];

            if ($qty <= 0) {
                throw new DomainException("Sertifikat putaway tidak valid: qty <= 0 pada bin {$binId}.");
            }
            if (isset($seenBins[$binId])) {
                throw new DomainException("Sertifikat putaway tidak valid: duplikat bin {$binId}.");
            }
            if (! isset($binMap[$binId]) || $qty > $binMap[$binId]) {
                throw new DomainException("Sertifikat putaway melampaui kapasitas bin {$binId}.");
            }

            $seenBins[$binId] = true;
            $totalAllocated += $qty;
        }

        if ($totalAllocated !== $certificate->requestedQty) {
            throw new DomainException(
                "Sertifikat putaway tidak seimbang: dialokasi {$totalAllocated}, diminta {$certificate->requestedQty}."
            );
        }

        return true;
    }
}
