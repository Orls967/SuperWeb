<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use InvalidArgumentException;

/**
 * Generator Jurnal Penyesuaian Konsolidasi Lanjutan (Advanced Consolidation Adjustments).
 *
 * Menangani penyesuaian kompleks sesuai PSAK / IFRS:
 * 1. Amortisasi selisih nilai wajar akuisisi aset teridentifikasi (Purchase Price Allocation / PPA Step-Up Amortization).
 * 2. Pembalikan liabilitas pajak tangguhan (Deferred Tax Liability / DTL Reversal) atas depresiasi selisih nilai wajar.
 * 3. Pengakuan laba/rugi entitas asosiasi menggunakan Metode Ekuitas (Equity Method - PSAK 15).
 * 4. Eliminasi transaksi sewa intra-grup (Intercompany Leases - PSAK 73 / IFRS 16: eliminasi aset hak-guna vs piutang sewa).
 * 5. Penurunan nilai unit penghasil kas (Cash Generating Unit / CGU Goodwill & Intangibles Impairment - PSAK 48).
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa tipe float.
 */
class ConsolidationAdjustmentJournalGenerator
{
    /**
     * Hasilkan jurnal amortisasi selisih nilai wajar aset teridentifikasi pasca-akuisisi (PPA Step-Up).
     *
     * @param array{
     *     acquisition_code: string,
     *     fiscal_period: string,
     *     subsidiary_entity_code: string,
     *     tax_rate_bps?: int
     * } $config
     * @param array<int, array{
     *     asset_name: string,
     *     asset_type: string, // TANGIBLE_PROPERTY, MACHINERY, CUSTOMER_RELATIONSHIP, TECHNOLOGY
     *     initial_fair_value_step_up_minor: int,
     *     useful_life_months: int,
     *     amortized_months_to_date: int
     * }> $revaluedAssets
     * @return array{
     *     total_amortization_minor: int,
     *     total_tax_benefit_minor: int,
     *     journals: array<int, array{
     *         journal_code: string,
     *         adjustment_category: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>
     * }
     */
    public function generatePpaAmortizationJournals(array $config, array $revaluedAssets): array
    {
        $period = $config['fiscal_period'];
        $sub = strtoupper($config['subsidiary_entity_code']);
        $taxRateBps = $config['tax_rate_bps'] ?? 2200; // PPh Badan 22.00%

        $journals = [];
        $totalAmortization = 0;
        $totalTaxBenefit = 0;
        $idx = 1;

        foreach ($revaluedAssets as $asset) {
            $stepUp = (int) $asset['initial_fair_value_step_up_minor'];
            $usefulMonths = max(1, (int) $asset['useful_life_months']);
            $amortizedMonths = (int) $asset['amortized_months_to_date'];

            if ($stepUp <= 0 || $amortizedMonths >= $usefulMonths) {
                continue;
            }

            // Amortisasi garis lurus per bulan (integer division)
            $monthlyAmortization = intdiv($stepUp, $usefulMonths);
            if ($monthlyAmortization <= 0) {
                continue;
            }

            $totalAmortization += $monthlyAmortization;

            // Pembalikan DTL: Amortisasi Step-Up * Tax Rate
            $dtlReversal = intdiv($monthlyAmortization * $taxRateBps, 10000);
            $totalTaxBenefit += $dtlReversal;

            // Jurnal 1: Beban Depresiasi/Amortisasi Nilai Wajar vs Akumulasi Depresiasi Nilai Wajar
            $journals[] = [
                'journal_code' => sprintf('PPA-AMORT-%s-%03d', $period, $idx++),
                'adjustment_category' => 'PPA_STEP_UP_AMORTIZATION',
                'debit_account' => 'CONSOLIDATED_DEPRECIATION_EXPENSE',
                'credit_account' => 'PPA_FAIR_VALUE_STEP_UP_ASSET',
                'amount_minor' => $monthlyAmortization,
                'memo' => "Amortisasi selisih nilai wajar ({$asset['asset_name']}) anak perusahaan {$sub} periode {$period}",
            ];

            // Jurnal 2: Pembalikan Liabilitas Pajak Tangguhan (DTL) ke Manfaat Pajak Tangguhan
            if ($dtlReversal > 0) {
                $journals[] = [
                    'journal_code' => sprintf('PPA-DTL-%s-%03d', $period, $idx++),
                    'adjustment_category' => 'DTL_REVERSAL',
                    'debit_account' => 'DEFERRED_TAX_LIABILITY',
                    'credit_account' => 'DEFERRED_TAX_BENEFIT_INCOME',
                    'amount_minor' => $dtlReversal,
                    'memo' => "Pembalikan liabilitas pajak tangguhan atas amortisasi PPA ({$asset['asset_name']})",
                ];
            }
        }

        return [
            'total_amortization_minor' => $totalAmortization,
            'total_tax_benefit_minor' => $totalTaxBenefit,
            'journals' => $journals,
        ];
    }

    /**
     * Hasilkan jurnal penyesuaian metode ekuitas untuk entitas asosiasi (PSAK 15 / IAS 28).
     *
     * @param array{
     *     associate_code: string,
     *     fiscal_period: string,
     *     holding_entity_code: string,
     *     ownership_bps: int, // 2000 - 5000 bps (20% - 50%)
     *     associate_net_income_minor: int,
     *     associate_oci_movement_minor: int,
     *     dividend_received_minor: int
     * } $data
     * @return array{
     *     share_of_profit_minor: int,
     *     share_of_oci_minor: int,
     *     net_investment_carrying_change_minor: int,
     *     journals: array<int, array{
     *         journal_code: string,
     *         adjustment_category: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>
     * }
     */
    public function generateEquityMethodJournals(array $data): array
    {
        $associate = strtoupper($data['associate_code']);
        $period = $data['fiscal_period'];
        $holding = strtoupper($data['holding_entity_code']);
        $bps = (int) $data['ownership_bps'];
        $netIncome = (int) $data['associate_net_income_minor'];
        $oci = (int) $data['associate_oci_movement_minor'];
        $dividend = (int) $data['dividend_received_minor'];

        if ($bps < 2000 || $bps >= 5000) {
            throw new InvalidArgumentException(
                "Metode ekuitas PSAK 15 hanya berlaku untuk pengaruh signifikan (2000-4999 bps / 20%-50%). Nilai: {$bps} bps"
            );
        }

        $shareOfProfit = intdiv($netIncome * $bps, 10000);
        $shareOfOci = intdiv($oci * $bps, 10000);

        // Perubahan bersih nilai tercatat investasi: Laba + OCI - Dividen Diterima
        $netCarryingChange = $shareOfProfit + $shareOfOci - $dividend;

        $journals = [];
        $idx = 1;

        // 1. Bagian Laba Bersih Entitas Asosiasi
        if ($shareOfProfit > 0) {
            $journals[] = [
                'journal_code' => sprintf('EQM-PROFIT-%s-%03d', $period, $idx++),
                'adjustment_category' => 'EQUITY_METHOD_PROFIT_PICKUP',
                'debit_account' => 'INVESTMENT_IN_ASSOCIATES',
                'credit_account' => 'SHARE_OF_PROFIT_OF_ASSOCIATES',
                'amount_minor' => $shareOfProfit,
                'memo' => "Pengakuan bagian laba ({$bps} bps) atas entitas asosiasi {$associate} periode {$period}",
            ];
        } elseif ($shareOfProfit < 0) {
            $journals[] = [
                'journal_code' => sprintf('EQM-LOSS-%s-%03d', $period, $idx++),
                'adjustment_category' => 'EQUITY_METHOD_LOSS_PICKUP',
                'debit_account' => 'SHARE_OF_PROFIT_OF_ASSOCIATES',
                'credit_account' => 'INVESTMENT_IN_ASSOCIATES',
                'amount_minor' => abs($shareOfProfit),
                'memo' => "Pengakuan bagian rugi ({$bps} bps) atas entitas asosiasi {$associate} periode {$period}",
            ];
        }

        // 2. Bagian Pendapatan Komprehensif Lain (OCI)
        if ($shareOfOci !== 0) {
            $journals[] = [
                'journal_code' => sprintf('EQM-OCI-%s-%03d', $period, $idx++),
                'adjustment_category' => 'EQUITY_METHOD_OCI_PICKUP',
                'debit_account' => $shareOfOci > 0 ? 'INVESTMENT_IN_ASSOCIATES' : 'OCI_EQUITY_RESERVE',
                'credit_account' => $shareOfOci > 0 ? 'OCI_EQUITY_RESERVE' : 'INVESTMENT_IN_ASSOCIATES',
                'amount_minor' => abs($shareOfOci),
                'memo' => "Pengakuan bagian OCI atas entitas asosiasi {$associate}",
            ];
        }

        // 3. Pengurangan Nilai Tercatat Investasi atas Dividen Kas yang Diterima
        if ($dividend > 0) {
            $journals[] = [
                'journal_code' => sprintf('EQM-DIV-%s-%03d', $period, $idx++),
                'adjustment_category' => 'EQUITY_METHOD_DIVIDEND_DEDUCTION',
                'debit_account' => 'IC_DIVIDEND_INCOME_ELIMINATION',
                'credit_account' => 'INVESTMENT_IN_ASSOCIATES',
                'amount_minor' => $dividend,
                'memo' => "Pengurangan nilai tercatat investasi atas dividen kas yang diterima dari asosiasi {$associate}",
            ];
        }

        return [
            'share_of_profit_minor' => $shareOfProfit,
            'share_of_oci_minor' => $shareOfOci,
            'net_investment_carrying_change_minor' => $netCarryingChange,
            'journals' => $journals,
        ];
    }

    /**
     * Hasilkan jurnal eliminasi sewa antar-perusahaan (PSAK 73 / IFRS 16 Leases).
     *
     * @param array{
     *     lease_agreement_code: string,
     *     fiscal_period: string,
     *     lessor_entity_code: string,
     *     lessee_entity_code: string,
     *     lease_payment_minor: int,
     *     interest_income_minor: int,
     *     interest_expense_minor: int,
     *     rou_depreciation_minor: int,
     *     lease_receivable_minor: int,
     *     lease_liability_minor: int
     * } $leaseData
     * @return array{
     *     total_eliminated_minor: int,
     *     journals: array<int, array{
     *         journal_code: string,
     *         adjustment_category: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>
     * }
     */
    public function generateIntercompanyLeaseEliminations(array $leaseData): array
    {
        $code = $leaseData['lease_agreement_code'];
        $period = $leaseData['fiscal_period'];
        $lessor = strtoupper($leaseData['lessor_entity_code']);
        $lessee = strtoupper($leaseData['lessee_entity_code']);

        $journals = [];
        $idx = 1;
        $totalEliminated = 0;

        // 1. Eliminasi Liabilitas Sewa Lessee vs Piutang Sewa Lessor
        $elimBalance = min($leaseData['lease_receivable_minor'], $leaseData['lease_liability_minor']);
        if ($elimBalance > 0) {
            $journals[] = [
                'journal_code' => sprintf('LEASE-ELIM-BAL-%s-%03d', $period, $idx++),
                'adjustment_category' => 'INTERCOMPANY_LEASE_BALANCE_ELIMINATION',
                'debit_account' => 'INTERCOMPANY_LEASE_LIABILITY',
                'credit_account' => 'INTERCOMPANY_LEASE_RECEIVABLE',
                'amount_minor' => $elimBalance,
                'memo' => "Eliminasi saldo liabilitas sewa {$lessee} dan piutang sewa {$lessor} untuk kontrak {$code}",
            ];
            $totalEliminated += $elimBalance;
        }

        // 2. Eliminasi Beban Bunga Lessee vs Pendapatan Bunga Lessor
        $elimInterest = min($leaseData['interest_income_minor'], $leaseData['interest_expense_minor']);
        if ($elimInterest > 0) {
            $journals[] = [
                'journal_code' => sprintf('LEASE-ELIM-INT-%s-%03d', $period, $idx++),
                'adjustment_category' => 'INTERCOMPANY_LEASE_INTEREST_ELIMINATION',
                'debit_account' => 'INTERCOMPANY_LEASE_INTEREST_INCOME',
                'credit_account' => 'INTERCOMPANY_LEASE_FINANCE_COST',
                'amount_minor' => $elimInterest,
                'memo' => "Eliminasi pendapatan dan beban bunga sewa intra-grup kontrak {$code}",
            ];
            $totalEliminated += $elimInterest;
        }

        return [
            'total_eliminated_minor' => $totalEliminated,
            'journals' => $journals,
        ];
    }

    /**
     * Hasilkan jurnal penurunan nilai aset dan goodwill (PSAK 48 / IAS 36 Impairment).
     *
     * @param array{
     *     cgu_code: string,
     *     fiscal_period: string,
     *     carrying_amount_minor: int,
     *     goodwill_carrying_minor: int,
     *     recoverable_amount_minor: int
     * } $impairmentData
     * @return array{
     *     impairment_loss_minor: int,
     *     goodwill_loss_minor: int,
     *     other_assets_loss_minor: int,
     *     journals: array<int, array{
     *         journal_code: string,
     *         adjustment_category: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>
     * }
     */
    public function generateIntangibleImpairmentJournals(array $impairmentData): array
    {
        $cgu = strtoupper($impairmentData['cgu_code']);
        $period = $impairmentData['fiscal_period'];
        $carrying = (int) $impairmentData['carrying_amount_minor'];
        $goodwill = (int) $impairmentData['goodwill_carrying_minor'];
        $recoverable = (int) $impairmentData['recoverable_amount_minor'];

        $impairmentLoss = max(0, $carrying - $recoverable);
        if ($impairmentLoss === 0) {
            return [
                'impairment_loss_minor' => 0,
                'goodwill_loss_minor' => 0,
                'other_assets_loss_minor' => 0,
                'journals' => [],
            ];
        }

        // Alokasikan rugi penurunan nilai pertama kali ke Goodwill
        $goodwillLoss = min($goodwill, $impairmentLoss);
        $otherAssetsLoss = $impairmentLoss - $goodwillLoss;

        $journals = [];
        $idx = 1;

        if ($goodwillLoss > 0) {
            $journals[] = [
                'journal_code' => sprintf('IMP-GW-%s-%03d', $period, $idx++),
                'adjustment_category' => 'GOODWILL_IMPAIRMENT',
                'debit_account' => 'IMPAIRMENT_LOSS_EXPENSE',
                'credit_account' => 'CONSOLIDATED_GOODWILL',
                'amount_minor' => $goodwillLoss,
                'memo' => "Penurunan nilai goodwill unit penghasil kas (CGU) {$cgu} periode {$period}",
            ];
        }

        if ($otherAssetsLoss > 0) {
            $journals[] = [
                'journal_code' => sprintf('IMP-AST-%s-%03d', $period, $idx++),
                'adjustment_category' => 'ASSET_IMPAIRMENT',
                'debit_account' => 'IMPAIRMENT_LOSS_EXPENSE',
                'credit_account' => 'ACCUMULATED_IMPAIRMENT_LOSSES',
                'amount_minor' => $otherAssetsLoss,
                'memo' => "Penurunan nilai aset berwujud dan takberwujud pro-rata unit penghasil kas {$cgu}",
            ];
        }

        return [
            'impairment_loss_minor' => $impairmentLoss,
            'goodwill_loss_minor' => $goodwillLoss,
            'other_assets_loss_minor' => $otherAssetsLoss,
            'journals' => $journals,
        ];
    }
}
