<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifikasi sertifikat jadwal ijarah (PR2):
 *  1. Σ ujrah = total ujrah
 *  2. Σ penyusutan = biaya − nilai sisa
 *  3. Jadwal kontigu tanpa celah (1..N)
 */
final class IjarahScheduleCertificateVerifier
{
    public function verify(IjarahResult $result): true
    {
        if (count($result->periods) !== $result->tenorMonths) {
            throw new InvalidCertificateException('Jumlah periode jadwal tidak sama dengan tenor bulan');
        }

        $sumU = 0;
        $sumD = 0;
        $expectedPeriod = 1;

        foreach ($result->periods as $p) {
            if ($p['period'] !== $expectedPeriod) {
                throw new InvalidCertificateException("Jadwal terputus pada periode {$p['period']}, diharapkan {$expectedPeriod}");
            }
            if ($p['ujrah_idr'] < 0 || $p['depreciation_idr'] < 0) {
                throw new InvalidCertificateException('Komponen ujrah atau penyusutan tidak boleh negatif');
            }

            $sumU += $p['ujrah_idr'];
            $sumD += $p['depreciation_idr'];
            $expectedPeriod++;
        }

        if ($sumU !== $result->totalUjrahIdr) {
            throw new InvalidCertificateException("Σ ujrah ({$sumU}) tidak sama dengan total ujrah ({$result->totalUjrahIdr})");
        }

        $expectedDeprec = max(0, $result->assetCostIdr - $result->residualOptionPriceIdr);
        if ($sumD !== $expectedDeprec) {
            throw new InvalidCertificateException("Σ penyusutan ({$sumD}) tidak sama dengan biaya - nilai sisa ({$expectedDeprec})");
        }

        $finalPeriod = $result->periods[count($result->periods) - 1];
        if ($finalPeriod['carrying_book_value_idr'] !== ($result->assetCostIdr - $sumD)) {
            throw new InvalidCertificateException('Nilai buku akhir tidak selaras dengan akumulasi penyusutan');
        }

        return true;
    }
}
