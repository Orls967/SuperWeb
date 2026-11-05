<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifikasi sertifikat distribusi asnaf (PR2):
 *  1. Σ alokasi = total dana zakat
 *  2. Alokasi amil <= 12.5% (1250 bps) dari total dana
 *  3. Seluruh 8 asnaf terdefinisi dengan alokasi >= 0
 */
final class AsnafDistributionCertificateVerifier
{
    public function verify(AsnafDistributionResult $result): true
    {
        $sum = 0;
        foreach ($result->allocations as $asnaf => $amount) {
            if ($amount < 0) {
                throw new InvalidCertificateException("Alokasi asnaf {$asnaf} bernilai negatif: {$amount}");
            }
            $sum += $amount;
        }

        if ($sum !== $result->totalZakatFundIdr) {
            throw new InvalidCertificateException("Σ alokasi asnaf ({$sum}) tidak sama dengan total dana ({$result->totalZakatFundIdr})");
        }

        $amilWeight = $result->weightsBps[AsnafDistribution::ASNAF_AMIL] ?? 0;
        if ($amilWeight > AsnafDistribution::MAX_AMIL_BPS) {
            throw new InvalidCertificateException("Bobot amil ({$amilWeight}) melebihi batas 1250 bps");
        }

        return true;
    }
}

