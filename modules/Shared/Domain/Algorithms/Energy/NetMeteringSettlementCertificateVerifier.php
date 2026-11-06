<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Certificate Verifier for E3 Energy\NetMeteringSettlement.
 *
 * Enforces (PR2):
 *  1. Per interval: ekspor = max(0, produksi - konsumsi), impor = max(0, konsumsi - produksi).
 *  2. Ekspor dan impor tidak boleh negatif.
 *  3. Σ ekspor dan Σ impor = total klaim.
 *  4. Tagihan = (impor × tarif) − (ekspor × feed_in).
 */
final class NetMeteringSettlementCertificateVerifier
{
    public function verify(NetMeteringSettlementResult $result): true
    {
        $sumExport = 0;
        $sumImport = 0;
        $sumProd = 0;
        $sumCons = 0;

        foreach ($result->intervals as $row) {
            $p = $row['production_kwh'];
            $c = $row['consumption_kwh'];
            $exp = $row['export_kwh'];
            $imp = $row['import_kwh'];

            if ($p < 0 || $c < 0 || $exp < 0 || $imp < 0) {
                throw new InvalidCertificateException("Nilai energi interval {$row['interval']} tidak boleh negatif");
            }

            $expectedExport = max(0, $p - $c);
            $expectedImport = max(0, $c - $p);

            if ($exp !== $expectedExport) {
                throw new InvalidCertificateException("Ekspor interval {$row['interval']} ({$exp}) tidak sesuai ({$expectedExport})");
            }

            if ($imp !== $expectedImport) {
                throw new InvalidCertificateException("Impor interval {$row['interval']} ({$imp}) tidak sesuai ({$expectedImport})");
            }

            $sumProd += $p;
            $sumCons += $c;
            $sumExport += $exp;
            $sumImport += $imp;
        }

        if ($sumExport !== $result->totalExportKwh) {
            throw new InvalidCertificateException("Total ekspor ({$result->totalExportKwh}) tidak sesuai dengan jumlah per interval ({$sumExport})");
        }

        if ($sumImport !== $result->totalImportKwh) {
            throw new InvalidCertificateException("Total impor ({$result->totalImportKwh}) tidak sesuai dengan jumlah per interval ({$sumImport})");
        }

        $expectedGrossImport = $sumImport * $result->tariffIdrPerKwh;
        if ($expectedGrossImport !== $result->grossImportCostIdr) {
            throw new InvalidCertificateException("Biaya impor kotor ({$result->grossImportCostIdr}) tidak cocok dengan {$sumImport} x {$result->tariffIdrPerKwh} = {$expectedGrossImport}");
        }

        $expectedCredit = $sumExport * $result->feedInTariffIdrPerKwh;
        if ($expectedCredit !== $result->exportCreditIdr) {
            throw new InvalidCertificateException("Kredit ekspor feed-in ({$result->exportCreditIdr}) tidak cocok dengan {$sumExport} x {$result->feedInTariffIdrPerKwh} = {$expectedCredit}");
        }

        $expectedNetBill = $expectedGrossImport - $expectedCredit;
        if ($expectedNetBill !== $result->netBillIdr) {
            throw new InvalidCertificateException("Tagihan bersih ({$result->netBillIdr}) tidak cocok dengan ({$expectedNetBill})");
        }

        return true;
    }
}
