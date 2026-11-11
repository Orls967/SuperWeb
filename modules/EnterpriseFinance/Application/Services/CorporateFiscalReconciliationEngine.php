<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Mesin Rekonsiliasi Fiskal Korporat (54.3 - UU Pajak Penghasilan).
 * Melakukan penyesuaian laba rugi komersial menjadi penghasilan kena pajak (PKP):
 * - Koreksi Fiskal Positif: Biaya representasi/entertainment non-daftar nominatif,
 *   sumbangan/donasi non-kualifikasi, sanksi/denda pajak, penyusutan komersial > fiskal, natura.
 * - Koreksi Fiskal Negatif: Penghasilan dividen non-objek pajak (UU Cipta Kerja),
 *   penghasilan bunga deposito (final), penyusutan fiskal > komersial.
 */
class CorporateFiscalReconciliationEngine
{
    /**
     * Tarif Pajak Penghasilan Badan Resmi (UU HPP: 22%).
     */
    public const STATUTORY_TAX_RATE_BPS = 2200; // 22.00%

    /**
     * Mengeksekusi rekonsiliasi fiskal dan menghitung PPh Badan terutang.
     *
     * @param  list<array{category: string, description: string, amount_idr: int}>  $positiveCorrections
     * @param  list<array{category: string, description: string, amount_idr: int}>  $negativeCorrections
     * @return array{
     *     fiscal_year: string,
     *     entity_code: string,
     *     commercial_net_profit_idr: int,
     *     total_positive_correction_idr: int,
     *     total_negative_correction_idr: int,
     *     fiscal_taxable_income_idr: int,
     *     corporate_tax_due_idr: int,
     *     effective_tax_rate_bps: int,
     *     tax_credits_idr: int,
     *     underpayment_pph29_idr: int
     * }
     */
    public function reconcile(
        string $fiscalYear,
        string $entityCode,
        int $commercialNetProfitIdr,
        array $positiveCorrections,
        array $negativeCorrections,
        int $taxCreditsPph22Pph23Pph25Idr = 0
    ): array {
        $totalPositive = 0;
        foreach ($positiveCorrections as $pc) {
            $amt = (int) $pc['amount_idr'];
            if ($amt < 0) {
                throw new InvalidArgumentException('Koreksi fiskal positif tidak boleh bernilai negatif.');
            }
            $totalPositive += $amt;
        }

        $totalNegative = 0;
        foreach ($negativeCorrections as $nc) {
            $amt = (int) $nc['amount_idr'];
            if ($amt < 0) {
                throw new InvalidArgumentException('Koreksi fiskal negatif tidak boleh bernilai negatif.');
            }
            $totalNegative += $amt;
        }

        // Penghasilan Kena Pajak (PKP) = Laba Komersial + Koreksi Positif - Koreksi Negatif
        $fiscalTaxableIncome = max(0, $commercialNetProfitIdr + $totalPositive - $totalNegative);

        // PPh Badan Terutang = PKP * 22%
        $taxDue = (int) intdiv($fiscalTaxableIncome * self::STATUTORY_TAX_RATE_BPS, 10000);

        // PPh Pasal 29 (Kurang Bayar) = PPh Terutang - Kredit Pajak
        $pph29 = max(0, $taxDue - $taxCreditsPph22Pph23Pph25Idr);

        // Effective Tax Rate (ETR) = (PPh Terutang / Laba Komersial) * 10000 bps
        $etrBps = ($commercialNetProfitIdr > 0)
            ? (int) intdiv($taxDue * 10000, $commercialNetProfitIdr)
            : 0;

        return [
            'fiscal_year' => $fiscalYear,
            'entity_code' => $entityCode,
            'commercial_net_profit_idr' => $commercialNetProfitIdr,
            'total_positive_correction_idr' => $totalPositive,
            'total_negative_correction_idr' => $totalNegative,
            'fiscal_taxable_income_idr' => $fiscalTaxableIncome,
            'corporate_tax_due_idr' => $taxDue,
            'effective_tax_rate_bps' => $etrBps,
            'tax_credits_idr' => $taxCreditsPph22Pph23Pph25Idr,
            'underpayment_pph29_idr' => $pph29,
        ];
    }
}
