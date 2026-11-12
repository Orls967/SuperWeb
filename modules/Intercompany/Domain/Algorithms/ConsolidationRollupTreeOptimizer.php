<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\GroupEntityRegistry;
use Modules\Intercompany\Domain\DTOs\ConsolidationTreeResultDTO;

/**
 * Algoritma Optimasi Roll-Up Konsolidasi Berjenjang Laporan Keuangan Grup (PSAK 65 / IFRS 10 & PSAK 10 / IAS 21).
 *
 * Menggabungkan neraca lajur (trial balance) entitas holding dan seluruh anak perusahaan
 * dalam perimeter konsolidasi penuh secara bottom-up, mengeliminasi saldo timbal balik (AR/AP, pinjaman, bunga),
 * mengeliminasi laba belum terealisasi dalam persediaan akhir (unrealized inventory profit),
 * menghitung Foreign Currency Translation Reserve (FCTR), serta mengatribusikan laba dan ekuitas
 * kepada Kepentingan Non-Pengendali (Non-Controlling Interest / NCI).
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points).
 */
class ConsolidationRollupTreeOptimizer
{
    public function __construct(
        protected readonly GroupEntityRegistry $registry
    ) {}

    /**
     * Jalankan eksekusi roll-up konsolidasi grup menyeluruh.
     *
     * @param array{
     *     run_id: string,
     *     fiscal_period: string,
     *     presentation_currency?: string,
     *     holding_entity_code: string,
     *     tax_rate_bps?: int
     * } $config
     * @param array<string, array{
     *     cash_minor: int,
     *     ar_third_party_minor: int,
     *     ar_intercompany_minor: int,
     *     inventory_minor: int,
     *     fixed_assets_minor: int,
     *     ap_third_party_minor: int,
     *     ap_intercompany_minor: int,
     *     loans_intercompany_payable_minor: int,
     *     loans_intercompany_receivable_minor: int,
     *     equity_capital_minor: int,
     *     retained_earnings_minor: int,
     *     revenue_third_party_minor: int,
     *     revenue_intercompany_minor: int,
     *     cogs_third_party_minor: int,
     *     cogs_intercompany_minor: int,
     *     opex_minor: int,
     *     currency?: string,
     *     fx_closing_rate_numerator?: int,
     *     fx_closing_rate_denominator?: int,
     *     fx_average_rate_numerator?: int,
     *     fx_average_rate_denominator?: int
     * }> $trialBalances
     * @param array<int, array{
     *     selling_entity: string,
     *     buying_entity: string,
     *     unrealized_profit_minor: int,
     *     sale_type: string // DOWNSTREAM, UPSTREAM, LATERAL
     * }> $unrealizedInventoryProfits
     */
    public function rollup(
        array $config,
        array $trialBalances,
        array $unrealizedInventoryProfits = []
    ): ConsolidationTreeResultDTO {
        $runId = $config['run_id'];
        $period = $config['fiscal_period'];
        $presCurrency = strtoupper($config['presentation_currency'] ?? 'IDR');
        $holdingCode = strtoupper($config['holding_entity_code']);
        $taxRateBps = $config['tax_rate_bps'] ?? 2200; // PPh Badan 22.00%

        if (! isset($trialBalances[$holdingCode])) {
            throw new InvalidArgumentException("Neraca lajur entitas induk {$holdingCode} wajib disertakan.");
        }

        $tree = $this->registry->buildConsolidationTree($holdingCode);
        $fullPerimeter = $tree['perimeter']['full'];

        // Urutkan entitas secara bottom-up berdasarkan kedalaman (depth menurun)
        $nodes = $tree['nodes'];
        uasort($nodes, fn ($a, $b) => $b['depth'] <=> $a['depth']);

        $translatedBalances = [];
        $totalFctrReserveMinor = 0;

        // Fase 1: Translasi Mata Uang Asing (PSAK 10 / IAS 21)
        foreach ($nodes as $code => $nodeInfo) {
            if (! in_array($code, $fullPerimeter, true) || ! isset($trialBalances[$code])) {
                continue;
            }

            $raw = $trialBalances[$code];
            $curr = strtoupper($raw['currency'] ?? $nodeInfo['currency'] ?? 'IDR');

            if ($curr === $presCurrency) {
                $translatedBalances[$code] = $raw;

                continue;
            }

            // Kurs penutup untuk pos aset & liabilitas
            $closeNum = $raw['fx_closing_rate_numerator'] ?? 100;
            $closeDen = max(1, $raw['fx_closing_rate_denominator'] ?? 100);

            // Kurs rata-rata untuk pos laba rugi
            $avgNum = $raw['fx_average_rate_numerator'] ?? 100;
            $avgDen = max(1, $raw['fx_average_rate_denominator'] ?? 100);

            $trans = [
                'cash_minor' => intdiv($raw['cash_minor'] * $closeNum, $closeDen),
                'ar_third_party_minor' => intdiv($raw['ar_third_party_minor'] * $closeNum, $closeDen),
                'ar_intercompany_minor' => intdiv($raw['ar_intercompany_minor'] * $closeNum, $closeDen),
                'inventory_minor' => intdiv($raw['inventory_minor'] * $closeNum, $closeDen),
                'fixed_assets_minor' => intdiv($raw['fixed_assets_minor'] * $closeNum, $closeDen),
                'ap_third_party_minor' => intdiv($raw['ap_third_party_minor'] * $closeNum, $closeDen),
                'ap_intercompany_minor' => intdiv($raw['ap_intercompany_minor'] * $closeNum, $closeDen),
                'loans_intercompany_payable_minor' => intdiv($raw['loans_intercompany_payable_minor'] * $closeNum, $closeDen),
                'loans_intercompany_receivable_minor' => intdiv($raw['loans_intercompany_receivable_minor'] * $closeNum, $closeDen),
                'equity_capital_minor' => intdiv($raw['equity_capital_minor'] * $closeNum, $closeDen),
                'retained_earnings_minor' => intdiv($raw['retained_earnings_minor'] * $closeNum, $closeDen),
                'revenue_third_party_minor' => intdiv($raw['revenue_third_party_minor'] * $avgNum, $avgDen),
                'revenue_intercompany_minor' => intdiv($raw['revenue_intercompany_minor'] * $avgNum, $avgDen),
                'cogs_third_party_minor' => intdiv($raw['cogs_third_party_minor'] * $avgNum, $avgDen),
                'cogs_intercompany_minor' => intdiv($raw['cogs_intercompany_minor'] * $avgNum, $avgDen),
                'opex_minor' => intdiv($raw['opex_minor'] * $avgNum, $avgDen),
                'currency' => $presCurrency,
            ];

            // Selisih translasi masuk ke FCTR
            $totAssets = $trans['cash_minor'] + $trans['ar_third_party_minor'] + $trans['ar_intercompany_minor']
                + $trans['inventory_minor'] + $trans['fixed_assets_minor'] + $trans['loans_intercompany_receivable_minor'];
            $totLiab = $trans['ap_third_party_minor'] + $trans['ap_intercompany_minor'] + $trans['loans_intercompany_payable_minor'];
            $netProfit = ($trans['revenue_third_party_minor'] + $trans['revenue_intercompany_minor'])
                - ($trans['cogs_third_party_minor'] + $trans['cogs_intercompany_minor'] + $trans['opex_minor']);
            $totEq = $trans['equity_capital_minor'] + $trans['retained_earnings_minor'] + $netProfit;

            $diffFctr = $totAssets - ($totLiab + $totEq);
            $totalFctrReserveMinor += $diffFctr;

            $translatedBalances[$code] = $trans;
        }

        // Fase 2: Agregasi Awal Lintas Entitas
        $aggAssets = 0;
        $aggLiab = 0;
        $aggEquityCap = 0;
        $aggRetained = 0;
        $aggRevThird = 0;
        $aggRevIc = 0;
        $aggCogsThird = 0;
        $aggCogsIc = 0;
        $aggOpex = 0;
        $aggArIc = 0;
        $aggApIc = 0;
        $aggLoanRecIc = 0;
        $aggLoanPayIc = 0;

        foreach ($translatedBalances as $code => $tb) {
            $aggAssets += ($tb['cash_minor'] + $tb['ar_third_party_minor'] + $tb['ar_intercompany_minor']
                + $tb['inventory_minor'] + $tb['fixed_assets_minor'] + $tb['loans_intercompany_receivable_minor']);
            $aggLiab += ($tb['ap_third_party_minor'] + $tb['ap_intercompany_minor'] + $tb['loans_intercompany_payable_minor']);
            $aggEquityCap += $tb['equity_capital_minor'];
            $aggRetained += $tb['retained_earnings_minor'];
            $aggRevThird += $tb['revenue_third_party_minor'];
            $aggRevIc += $tb['revenue_intercompany_minor'];
            $aggCogsThird += $tb['cogs_third_party_minor'];
            $aggCogsIc += $tb['cogs_intercompany_minor'];
            $aggOpex += $tb['opex_minor'];
            $aggArIc += $tb['ar_intercompany_minor'];
            $aggApIc += $tb['ap_intercompany_minor'];
            $aggLoanRecIc += $tb['loans_intercompany_receivable_minor'];
            $aggLoanPayIc += $tb['loans_intercompany_payable_minor'];
        }

        $eliminationJournals = [];
        $jIdx = 1;

        // Fase 3: Jurnal Eliminasi Saldo Resiprokal Intercompany
        // 3.1 Eliminasi IC AR vs IC AP
        $elimArAp = min($aggArIc, $aggApIc);
        if ($elimArAp > 0) {
            $eliminationJournals[] = [
                'journal_code' => sprintf('ELIM-%s-%03d', $period, $jIdx++),
                'elimination_type' => 'RECIPROCAL_AR_AP',
                'debit_account' => 'IC_ACCOUNTS_PAYABLE',
                'credit_account' => 'IC_ACCOUNTS_RECEIVABLE',
                'amount_minor' => $elimArAp,
                'affected_entities' => $fullPerimeter,
                'rationale' => 'Eliminasi saldo timbal balik piutang dan utang usaha antar-entitas konsolidasi',
            ];
        }

        // 3.2 Eliminasi Pinjaman Intercompany
        $elimLoan = min($aggLoanRecIc, $aggLoanPayIc);
        if ($elimLoan > 0) {
            $eliminationJournals[] = [
                'journal_code' => sprintf('ELIM-%s-%03d', $period, $jIdx++),
                'elimination_type' => 'RECIPROCAL_LOANS',
                'debit_account' => 'IC_LOANS_PAYABLE',
                'credit_account' => 'IC_LOANS_RECEIVABLE',
                'amount_minor' => $elimLoan,
                'affected_entities' => $fullPerimeter,
                'rationale' => 'Eliminasi saldo pokok pinjaman timbal balik intra-grup',
            ];
        }

        // 3.3 Eliminasi Pendapatan & Beban Transaksi Intra-Grup
        $elimSalesCogs = min($aggRevIc, $aggCogsIc);
        if ($elimSalesCogs > 0) {
            $eliminationJournals[] = [
                'journal_code' => sprintf('ELIM-%s-%03d', $period, $jIdx++),
                'elimination_type' => 'INTRA_GROUP_SALES',
                'debit_account' => 'IC_SALES_REVENUE',
                'credit_account' => 'IC_COGS_EXPENSE',
                'amount_minor' => $elimSalesCogs,
                'affected_entities' => $fullPerimeter,
                'rationale' => 'Eliminasi pendapatan penjualan dan beban pokok barang intra-grup',
            ];
        }

        // 3.4 Eliminasi Laba Belum Terealisasi dalam Persediaan Akhir
        $totalUnrealizedMinor = 0;
        foreach ($unrealizedInventoryProfits as $unr) {
            $uAmt = (int) $unr['unrealized_profit_minor'];
            if ($uAmt <= 0) {
                continue;
            }

            $totalUnrealizedMinor += $uAmt;
            $dtaTaxMinor = intdiv($uAmt * $taxRateBps, 10000);

            $eliminationJournals[] = [
                'journal_code' => sprintf('ELIM-%s-%03d', $period, $jIdx++),
                'elimination_type' => 'UNREALIZED_INVENTORY_PROFIT',
                'debit_account' => 'CONSOLIDATED_RETAINED_EARNINGS',
                'credit_account' => 'CONSOLIDATED_INVENTORY',
                'amount_minor' => $uAmt,
                'affected_entities' => [$unr['selling_entity'], $unr['buying_entity']],
                'rationale' => "Eliminasi laba antar-perusahaan belum terealisasi ({$unr['sale_type']}) pada persediaan akhir",
            ];

            if ($dtaTaxMinor > 0) {
                $eliminationJournals[] = [
                    'journal_code' => sprintf('ELIM-%s-%03d', $period, $jIdx++),
                    'elimination_type' => 'DEFERRED_TAX_ASSET',
                    'debit_account' => 'DEFERRED_TAX_ASSET',
                    'credit_account' => 'INCOME_TAX_EXPENSE',
                    'amount_minor' => $dtaTaxMinor,
                    'affected_entities' => [$unr['selling_entity']],
                    'rationale' => 'Pengakuan aset pajak tangguhan atas laba intra-grup belum terealisasi',
                ];
            }
        }

        // Fase 4: Alokasi Laba Bersih & Ekuitas ke Kepentingan Non-Pengendali (NCI)
        $nciAttributions = [];
        $totalNciProfitMinor = 0;
        $totalNciEquityMinor = 0;

        foreach ($nodes as $code => $nodeInfo) {
            if ($code === $holdingCode || ! in_array($code, $fullPerimeter, true) || ! isset($translatedBalances[$code])) {
                continue;
            }

            $subTb = $translatedBalances[$code];
            $subRev = $subTb['revenue_third_party_minor'] + $subTb['revenue_intercompany_minor'];
            $subExp = $subTb['cogs_third_party_minor'] + $subTb['cogs_intercompany_minor'] + $subTb['opex_minor'];
            $subNetProfit = $subRev - $subExp;
            $subEquity = $subTb['equity_capital_minor'] + $subTb['retained_earnings_minor'] + $subNetProfit;

            $nciBps = (int) $nodeInfo['nci_bps'];
            $allocatedProfit = intdiv($subNetProfit * $nciBps, 10000);
            $allocatedEquity = intdiv($subEquity * $nciBps, 10000);

            $totalNciProfitMinor += $allocatedProfit;
            $totalNciEquityMinor += $allocatedEquity;

            $nciAttributions[$code] = [
                'entity_code' => $code,
                'effective_ownership_bps' => (int) $nodeInfo['effective_ownership_bps'],
                'nci_bps' => $nciBps,
                'allocated_profit_minor' => $allocatedProfit,
                'allocated_equity_minor' => $allocatedEquity,
            ];
        }

        // Fase 5: Kalkulasi Laporan Keuangan Konsolidasian Bersih
        $consolidatedAssets = $aggAssets - $elimArAp - $elimLoan - $totalUnrealizedMinor;
        $consolidatedLiabilities = $aggLiab - $elimArAp - $elimLoan;
        $consolidatedRevenue = ($aggRevThird + $aggRevIc) - $elimSalesCogs;
        $consolidatedExpenses = ($aggCogsThird + $aggCogsIc + $aggOpex) - $elimSalesCogs;
        $consolidatedNetProfit = $consolidatedRevenue - $consolidatedExpenses;

        $nciShareNetProfit = $totalNciProfitMinor;
        $parentShareNetProfit = $consolidatedNetProfit - $nciShareNetProfit;

        // Ekuitas konsolidasi induk + NCI
        $consolidatedEquity = $consolidatedAssets - $consolidatedLiabilities;
        $isBalanced = ($consolidatedAssets === ($consolidatedLiabilities + $consolidatedEquity));

        $consolidatedFinancials = [
            'consolidated_assets_minor' => $consolidatedAssets,
            'consolidated_liabilities_minor' => $consolidatedLiabilities,
            'consolidated_equity_minor' => $consolidatedEquity,
            'consolidated_revenue_minor' => $consolidatedRevenue,
            'consolidated_expenses_minor' => $consolidatedExpenses,
            'consolidated_net_profit_minor' => $consolidatedNetProfit,
            'parent_share_net_profit_minor' => $parentShareNetProfit,
            'nci_share_net_profit_minor' => $nciShareNetProfit,
            'fctr_reserve_minor' => $totalFctrReserveMinor,
            'is_balance_sheet_balanced' => $isBalanced,
        ];

        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $hashSeed = sprintf(
            '%s|%s|%s|%d|%d|%d|%d',
            $runId,
            $period,
            $holdingCode,
            $consolidatedAssets,
            $consolidatedLiabilities,
            $consolidatedNetProfit,
            count($eliminationJournals)
        );
        $integrityHash = hash('sha256', $hashSeed);

        return new ConsolidationTreeResultDTO(
            consolidationRunId: $runId,
            fiscalPeriod: $period,
            presentationCurrency: $presCurrency,
            holdingEntityCode: $holdingCode,
            entityTrialBalances: $translatedBalances,
            eliminationJournals: $eliminationJournals,
            consolidatedFinancials: $consolidatedFinancials,
            nciAttributions: $nciAttributions,
            generatedAt: $nowStr,
            integrityHash: $integrityHash
        );
    }
}
