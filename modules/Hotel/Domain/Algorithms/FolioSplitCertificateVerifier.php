<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Verifikator Matematis & Konservasi Nilai untuk FolioSplitCertificate.
 */
final class FolioSplitCertificateVerifier
{
    public function verify(FolioSplitCertificate $cert): bool
    {
        // 1. Verifikasi Konservasi Saldo (Invarian: Total = Corporate + Personal)
        if ($cert->totalAmountIdr !== ($cert->corporateAmountIdr + $cert->personalAmountIdr)) {
            return false;
        }

        // 2. Verifikasi Agregasi Alokasi Detail
        $sumCorp = 0;
        $sumPersonal = 0;
        foreach ($cert->allocations as $item) {
            if ($item['amount_idr'] < 0) {
                return false;
            }
            if ($item['payer'] === 'CORPORATE') {
                $sumCorp += $item['amount_idr'];
            } elseif ($item['payer'] === 'PERSONAL') {
                $sumPersonal += $item['amount_idr'];
            } else {
                return false;
            }
        }

        if ($sumCorp !== $cert->corporateAmountIdr || $sumPersonal !== $cert->personalAmountIdr) {
            return false;
        }

        // 3. Verifikasi Checksum Hash
        $expectedChecksum = hash('sha256', sprintf(
            '%s|%d|%d|%d|%d',
            $cert->folioId,
            $cert->totalAmountIdr,
            $cert->corporateAmountIdr,
            $cert->personalAmountIdr,
            count($cert->allocations)
        ));

        return hash_equals($expectedChecksum, $cert->checksum);
    }
}
