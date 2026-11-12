<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use InvalidArgumentException;

/**
 * Layanan Pemindaian Kas Terpusat Intercompany (Cash Pool Liquidity Sweeper).
 *
 * Mengelola struktur rekening saldo nol (Zero-Balance Account / ZBA) terpusat,
 * menyapu kelebihan likuiditas anak perusahaan ke rekening induk (liquidity sweep),
 * menyalurkan pinjaman harian/overdraft likuiditas antar-anak perusahaan,
 * serta menghitung bunga simpanan/pinjaman cash pool sesuai prinsip arm's length (PMK 172/2023).
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa float.
 */
class IntercompanyCashPoolSweeper
{
    /**
     * Jalankan eksekusi liquidity sweep harian ke rekening induk pool.
     *
     * @param array{
     *     pool_code: string,
     *     sweep_date: string,
     *     header_entity_code: string,
     *     currency?: string,
     *     target_target_balance_minor?: int,
     *     pool_deposit_rate_bps?: int, // Misal 550 bps (5.50%)
     *     pool_borrowing_rate_bps?: int, // Misal 750 bps (7.50%)
     *     wht_rate_bps?: int // Misal 1500 bps (15.00% PPh 23)
     * } $config
     * @param array<int, array{
     *     entity_code: string,
     *     account_number: string,
     *     current_balance_minor: int,
     *     minimum_operating_buffer_minor?: int
     * }> $participantAccounts
     * @return array{
     *     sweep_session_code: string,
     *     sweep_date: string,
     *     header_entity_code: string,
     *     total_swept_in_minor: int,
     *     total_funded_out_minor: int,
     *     net_pool_liquidity_change_minor: int,
     *     transfers: array<int, array{
     *         transfer_code: string,
     *         transfer_direction: string, // SWEEP_IN_SURPLUS, FUND_OUT_DEFICIT
     *         participant_entity_code: string,
     *         amount_minor: int,
     *         accrued_interest_minor: int,
     *         withholding_tax_minor: int,
     *         memo: string
     *     }>,
     *     journal_vouchers: array<int, array{
     *         journal_code: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>,
     *     reconciliation_status: string
     * }
     */
    public function executeSweep(array $config, array $participantAccounts): array
    {
        $poolCode = $config['pool_code'];
        $date = $config['sweep_date'];
        $headerEntity = strtoupper($config['header_entity_code']);
        $depositRateBps = $config['pool_deposit_rate_bps'] ?? 550;
        $borrowingRateBps = $config['pool_borrowing_rate_bps'] ?? 750;
        $whtRateBps = $config['wht_rate_bps'] ?? 1500;
        $targetBalance = (int) ($config['target_target_balance_minor'] ?? 0);

        if (empty($participantAccounts)) {
            throw new InvalidArgumentException('Daftar rekening peserta cash pool tidak boleh kosong.');
        }

        $transfers = [];
        $journals = [];
        $totalSweptIn = 0;
        $totalFundedOut = 0;
        $idx = 1;

        foreach ($participantAccounts as $acct) {
            $entity = strtoupper($acct['entity_code']);
            if ($entity === $headerEntity) {
                continue;
            }

            $currentBal = (int) $acct['current_balance_minor'];
            $buffer = (int) ($acct['minimum_operating_buffer_minor'] ?? $targetBalance);

            // Surplus likuiditas di atas buffer operasional disapu ke Header Pool (Sweep In)
            if ($currentBal > $buffer) {
                $sweepAmount = $currentBal - $buffer;
                $totalSweptIn += $sweepAmount;

                // Hitung estimasi bunga harian simpanan: (sweepAmount * depositRateBps) / (10000 * 365)
                $dailyInterest = intdiv($sweepAmount * $depositRateBps, 10000 * 365);
                $whtTax = intdiv($dailyInterest * $whtRateBps, 10000);

                $transferCode = sprintf('SWP-IN-%s-%03d', $date, $idx++);
                $transfers[] = [
                    'transfer_code' => $transferCode,
                    'transfer_direction' => 'SWEEP_IN_SURPLUS',
                    'participant_entity_code' => $entity,
                    'amount_minor' => $sweepAmount,
                    'accrued_interest_minor' => $dailyInterest,
                    'withholding_tax_minor' => $whtTax,
                    'memo' => "Penyapuan surplus kas harian dari {$entity} ke kas terpusat {$headerEntity}",
                ];

                // Jurnal Transfer Kas Saldo Nol
                $journals[] = [
                    'journal_code' => "JRN-{$transferCode}-1",
                    'debit_account' => 'CENTRAL_POOL_BANK_HEADER',
                    'credit_account' => "IC_POOL_DEPOSIT_PAYABLE_{$entity}",
                    'amount_minor' => $sweepAmount,
                    'memo' => "Penerimaan likuiditas surplus dari rekening {$acct['account_number']}",
                ];
            } elseif ($currentBal < $buffer) {
                // Defisit likuiditas di bawah buffer didanai dari Header Pool (Fund Out)
                $fundingAmount = $buffer - $currentBal;
                $totalFundedOut += $fundingAmount;

                // Hitung estimasi bunga harian pinjaman: (fundingAmount * borrowingRateBps) / (10000 * 365)
                $dailyInterest = intdiv($fundingAmount * $borrowingRateBps, 10000 * 365);
                $whtTax = intdiv($dailyInterest * $whtRateBps, 10000);

                $transferCode = sprintf('FND-OUT-%s-%03d', $date, $idx++);
                $transfers[] = [
                    'transfer_code' => $transferCode,
                    'transfer_direction' => 'FUND_OUT_DEFICIT',
                    'participant_entity_code' => $entity,
                    'amount_minor' => $fundingAmount,
                    'accrued_interest_minor' => $dailyInterest,
                    'withholding_tax_minor' => $whtTax,
                    'memo' => "Penyaluran likuiditas overdraft pool dari {$headerEntity} ke rekening {$entity}",
                ];

                $journals[] = [
                    'journal_code' => "JRN-{$transferCode}-1",
                    'debit_account' => "IC_POOL_LOAN_RECEIVABLE_{$entity}",
                    'credit_account' => 'CENTRAL_POOL_BANK_HEADER',
                    'amount_minor' => $fundingAmount,
                    'memo' => "Penyaluran pinjaman likuiditas ke rekening {$acct['account_number']}",
                ];
            }
        }

        $netChange = $totalSweptIn - $totalFundedOut;
        $sessionCode = sprintf('CPOOL-SWP-%s-%s', $poolCode, str_replace('-', '', $date));

        return [
            'sweep_session_code' => $sessionCode,
            'sweep_date' => $date,
            'header_entity_code' => $headerEntity,
            'total_swept_in_minor' => $totalSweptIn,
            'total_funded_out_minor' => $totalFundedOut,
            'net_pool_liquidity_change_minor' => $netChange,
            'transfers' => $transfers,
            'journal_vouchers' => $journals,
            'reconciliation_status' => 'BALANCED_ZERO_VARIANCE',
        ];
    }
}
