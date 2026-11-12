<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Intercompany\Domain\DTOs\NciCascadeResultDTO;

/**
 * Algoritma Cascade Dividen dan Pemotongan Pajak Kepentingan Non-Pengendali (NCI Dividend & Tax Cascade).
 *
 * Mengatur distribusi dividen berjenjang dari anak perusahaan ke induk dan pemegang saham eksternal (NCI),
 * memperhitungkan cadangan wajib statutori (UU PT No. 40/2007), fasilitas pembebasan PPh 23 dividen intra-grup (UU HPP),
 * dan pemotongan PPh 26 lintas batas dengan tarif reduksi Perjanjian Penghindaran Pajak Berganda (P3B / Tax Treaty).
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points).
 */
class NciDividendCascadeCalculator
{
    /**
     * Jalankan kalkulasi cascade dividen dan potongan pajak.
     *
     * @param array{
     *     declaration_code: string,
     *     distributing_entity_code: string,
     *     fiscal_year: string,
     *     currency?: string,
     *     gross_dividend_minor: int,
     *     retained_earnings_minor: int,
     *     issued_capital_minor: int,
     *     existing_statutory_reserve_minor: int,
     *     parent_entity_code: string,
     *     parent_ownership_bps: int
     * } $declarationConfig
     * @param array<int, array{
     *     name: string,
     *     type: string, // DOMESTIC_CORPORATE, DOMESTIC_INDIVIDUAL, FOREIGN_CORPORATE
     *     ownership_bps: int,
     *     country_code?: string,
     *     has_valid_dgt1_cor?: bool,
     *     tax_treaty_rate_bps?: int
     * }> $nciShareholders
     */
    public function calculate(
        array $declarationConfig,
        array $nciShareholders
    ): NciCascadeResultDTO {
        $code = $declarationConfig['declaration_code'];
        $entityCode = strtoupper($declarationConfig['distributing_entity_code']);
        $year = $declarationConfig['fiscal_year'];
        $curr = strtoupper($declarationConfig['currency'] ?? 'IDR');
        $grossDividend = (int) $declarationConfig['gross_dividend_minor'];
        $retainedEarnings = (int) $declarationConfig['retained_earnings_minor'];
        $issuedCapital = (int) $declarationConfig['issued_capital_minor'];
        $existingReserve = (int) $declarationConfig['existing_statutory_reserve_minor'];
        $parentCode = strtoupper($declarationConfig['parent_entity_code']);
        $parentBps = (int) $declarationConfig['parent_ownership_bps'];

        if ($grossDividend <= 0) {
            throw new InvalidArgumentException('Nilai dividen yang dideklarasikan harus lebih besar dari nol.');
        }

        // 1. Validasi Cadangan Wajib Statutori (Pasal 70 UU PT: cadangan wajib minimal 20% modal disetor)
        $targetStatutoryReserve = intdiv($issuedCapital * 2000, 10000); // 20%
        $reserveDeficit = max(0, $targetStatutoryReserve - $existingReserve);

        // Alokasikan minimal 10% laba ditahan untuk menambah cadangan bila belum mencapai 20%
        $statutoryAllocation = 0;
        if ($reserveDeficit > 0 && $retainedEarnings > 0) {
            $statutoryAllocation = min($reserveDeficit, intdiv($retainedEarnings * 1000, 10000));
        }

        $distributableEarnings = max(0, $retainedEarnings - $statutoryAllocation);
        if ($grossDividend > $distributableEarnings) {
            throw new InvalidArgumentException(
                "Dividen yang dideklarasikan (Rp {$grossDividend}) melebihi saldo laba yang dapat didistribusikan (Rp {$distributableEarnings}) setelah pembentukan cadangan wajib statutori."
            );
        }

        // 2. Validasi Total Kepemilikan Saham (Induk + NCI = 10000 bps / 100.00%)
        $sumNciBps = 0;
        foreach ($nciShareholders as $sh) {
            $sumNciBps += (int) $sh['ownership_bps'];
        }

        if ($parentBps + $sumNciBps !== 10000) {
            throw new InvalidArgumentException(
                "Total persentase saham harus tepat 10000 bps (100%). Induk: {$parentBps} bps, NCI: {$sumNciBps} bps (Total: ".($parentBps + $sumNciBps).' bps).'
            );
        }

        $distributions = [];
        $ledgerEntries = [];
        $entryIdx = 1;

        // 3. Alokasi Dividen ke Entitas Induk (Holding)
        // Berdasarkan UU HPP / UU Cipta Kerja: Dividen antar-perusahaan domestik dibebaskan dari pemotongan PPh 23 (0% WHT)
        $parentGrossDividend = intdiv($grossDividend * $parentBps, 10000);
        $distributions[] = [
            'shareholder_type' => 'PARENT_HOLDING',
            'shareholder_name' => "Induk {$parentCode}",
            'ownership_bps' => $parentBps,
            'gross_dividend_minor' => $parentGrossDividend,
            'wht_rate_bps' => 0,
            'wht_tax_minor' => 0,
            'net_dividend_payable_minor' => $parentGrossDividend,
            'is_eliminated_in_consolidation' => true,
            'tax_treaty_applied' => null,
        ];

        // Jurnal Eliminasi Dividen Induk pada Konsolidasi
        $ledgerEntries[] = [
            'entry_type' => 'ELIM_PARENT_DIVIDEND',
            'debit_account' => 'IC_DIVIDEND_INCOME',
            'credit_account' => 'IC_DIVIDEND_DISTRIBUTION',
            'amount_minor' => $parentGrossDividend,
            'memo' => "Eliminasi dividen intra-grup dari {$entityCode} ke {$parentCode}",
        ];

        // 4. Alokasi Dividen ke Pemegang Saham Eksternal (NCI) & Perhitungan PPh
        $totalNciGross = 0;
        $totalWhtWithheld = 0;
        $totalNetCashOutflow = 0;

        foreach ($nciShareholders as $nci) {
            $nciBps = (int) $nci['ownership_bps'];
            $nciGross = intdiv($grossDividend * $nciBps, 10000);
            $totalNciGross += $nciGross;

            $shType = strtoupper($nci['type']);
            $whtRateBps = 1500; // Standar PPh 23: 15.00%
            $treatyApplied = null;

            if ($shType === 'FOREIGN_CORPORATE') {
                $whtRateBps = 2000; // Standar PPh 26 luar negeri: 20.00%
                $hasDgt1 = (bool) ($nci['has_valid_dgt1_cor'] ?? false);
                $treatyRate = (int) ($nci['tax_treaty_rate_bps'] ?? 0);

                if ($hasDgt1 && $treatyRate > 0 && $treatyRate < 2000) {
                    $whtRateBps = $treatyRate;
                    $country = strtoupper($nci['country_code'] ?? 'FOREIGN');
                    $treatyApplied = "P3B_{$country}_{$treatyRate}BPS";
                }
            } elseif ($shType === 'DOMESTIC_INDIVIDUAL') {
                $whtRateBps = 1000; // PPh Final Dividen Orang Pribadi: 10.00% (Pasal 17 ayat 2c UU PPh)
            }

            $whtAmount = intdiv($nciGross * $whtRateBps, 10000);
            $netPayable = $nciGross - $whtAmount;

            $totalWhtWithheld += $whtAmount;
            $totalNetCashOutflow += $netPayable;

            $distributions[] = [
                'shareholder_type' => $shType,
                'shareholder_name' => $nci['name'],
                'ownership_bps' => $nciBps,
                'gross_dividend_minor' => $nciGross,
                'wht_rate_bps' => $whtRateBps,
                'wht_tax_minor' => $whtAmount,
                'net_dividend_payable_minor' => $netPayable,
                'is_eliminated_in_consolidation' => false,
                'tax_treaty_applied' => $treatyApplied,
            ];

            // Jurnal Hutang Dividen NCI & Hutang PPh
            $ledgerEntries[] = [
                'entry_type' => 'NCI_DIVIDEND_PAYABLE',
                'debit_account' => 'RETAINED_EARNINGS',
                'credit_account' => 'NCI_DIVIDEND_PAYABLE',
                'amount_minor' => $netPayable,
                'memo' => "Hutang dividen bersih NCI untuk {$nci['name']}",
            ];

            if ($whtAmount > 0) {
                $ledgerEntries[] = [
                    'entry_type' => 'TAX_WITHHOLDING_PAYABLE',
                    'debit_account' => 'RETAINED_EARNINGS',
                    'credit_account' => 'TAX_PAYABLE_WHT_DIVIDEND',
                    'amount_minor' => $whtAmount,
                    'memo' => "Hutang potongan pajak dividen {$whtRateBps} bps untuk {$nci['name']}",
                ];
            }
        }

        // Ringkasan Metrik
        $summaryMetrics = [
            'total_declared_gross_minor' => $grossDividend,
            'parent_share_gross_minor' => $parentGrossDividend,
            'nci_share_gross_minor' => $totalNciGross,
            'total_wht_withheld_minor' => $totalWhtWithheld,
            'total_net_cash_outflow_minor' => $totalNetCashOutflow,
            'statutory_reserve_retained_minor' => $statutoryAllocation,
        ];

        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $hashSeed = sprintf(
            '%s|%s|%s|%d|%d|%d|%d',
            $code,
            $entityCode,
            $year,
            $grossDividend,
            $parentGrossDividend,
            $totalNciGross,
            $totalWhtWithheld
        );
        $authorizationHash = hash('sha256', $hashSeed);

        return new NciCascadeResultDTO(
            declarationCode: $code,
            distributingEntityCode: $entityCode,
            fiscalYear: $year,
            currency: $curr,
            shareholderDistributions: $distributions,
            summaryMetrics: $summaryMetrics,
            ledgerEntries: $ledgerEntries,
            calculatedAt: $nowStr,
            authorizationHash: $authorizationHash
        );
    }
}
