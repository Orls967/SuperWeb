<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

class IntercompanyService
{
    /**
     * 52.1 Transaksi Antar-Entitas Grup (Mirror Transaction SO/PO)
     */
    public function recordMirrorTransaction(
        string $sellingEntity,
        string $buyingEntity,
        string $description,
        int $amountIdr,
        string $currency = 'IDR'
    ): IntercompanyTransaction {
        if ($sellingEntity === $buyingEntity) {
            throw new InvalidArgumentException('Transaksi intercompany tidak boleh antara entitas yang sama.');
        }

        $code = 'IC-TX-'.strtoupper(Str::random(8));

        return IntercompanyTransaction::create([
            'transaction_code' => $code,
            'selling_entity' => $sellingEntity,
            'buying_entity' => $buyingEntity,
            'description' => $description,
            'currency' => strtoupper($currency),
            'amount_idr' => $amountIdr,
            'sales_invoice_ref' => 'INV-'.$code,
            'purchase_bill_ref' => 'BILL-'.$code,
            'status' => 'matched',
        ]);
    }

    public function issueIntercompanyLoan(
        string $lender,
        string $borrower,
        int $principalIdr,
        float $armsLengthRate,
        string $dueDate
    ): IntercompanyLoan {
        if ($lender === $borrower) {
            throw new InvalidArgumentException('Pinjaman intercompany memerlukan entitas peminjam dan pemberi pinjaman berbeda.');
        }

        return IntercompanyLoan::create([
            'loan_agreement_number' => 'ICL-'.strtoupper(Str::random(8)),
            'lender_entity' => $lender,
            'borrower_entity' => $borrower,
            'principal_idr' => $principalIdr,
            'arms_length_interest_rate' => $armsLengthRate,
            'start_date' => Carbon::today()->toDateString(),
            'due_date' => $dueDate,
            'repaid_principal_idr' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * 52.2 Transfer Pricing Engine: Validasi Margin Arm's Length
     */
    public function registerTransferPricingRule(
        string $category,
        string $tpMethod,
        float $minMargin,
        float $maxMargin,
        string $benchmarkSource = 'OECD_STAT_2026'
    ): TransferPricingRule {
        return TransferPricingRule::create([
            'rule_code' => 'TPR-'.strtoupper(Str::random(6)),
            'product_category' => $category,
            'tp_method' => strtoupper($tpMethod),
            'min_arms_length_margin_percent' => $minMargin,
            'max_arms_length_margin_percent' => $maxMargin,
            'benchmark_industry_source' => $benchmarkSource,
            'is_active' => true,
        ]);
    }

    public function validateTransferPricingMargin(
        string $category,
        int $costIdr,
        int $intercompanyPriceIdr
    ): array {
        $rule = TransferPricingRule::where('product_category', $category)->where('is_active', true)->first();

        if (! $rule) {
            return [
                'status' => 'UNREGULATED',
                'is_compliant' => true,
                'margin_percent' => 0.0,
            ];
        }

        $marginPercent = (($intercompanyPriceIdr - $costIdr) / $costIdr) * 100.0;
        $isCompliant = ($marginPercent >= $rule->min_arms_length_margin_percent && $marginPercent <= $rule->max_arms_length_margin_percent);

        return [
            'status' => $isCompliant ? 'COMPLIANT' : 'OUT_OF_RANGE',
            'is_compliant' => $isCompliant,
            'actual_margin_percent' => round($marginPercent, 2),
            'benchmark_range' => "{$rule->min_arms_length_margin_percent}% - {$rule->max_arms_length_margin_percent}%",
            'tp_method' => $rule->tp_method,
        ];
    }

    /**
     * 52.4 Mesin Eliminasi Konsolidasi
     */
    public function postEliminationEntry(
        string $period,
        string $eliminationType,
        string $debitAccount,
        string $creditAccount,
        int $amountIdr
    ): EliminationEntry {
        return EliminationEntry::create([
            'elimination_code' => 'ELIM-'.strtoupper(Str::random(8)),
            'period' => $period,
            'elimination_type' => $eliminationType,
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'amount_idr' => $amountIdr,
            'status' => 'posted',
        ]);
    }

    /**
     * 52.5 Non-Controlling Interest (NCI)
     */
    public function calculateNciShare(
        string $subsidiaryName,
        float $parentSharePercent,
        int $subsidiaryNetIncomeIdr
    ): SubsidiaryNci {
        $nciSharePercent = 100.0 - $parentSharePercent;
        $nciIncome = (int) floor(($subsidiaryNetIncomeIdr * $nciSharePercent) / 100);

        return SubsidiaryNci::updateOrCreate(
            ['subsidiary_name' => $subsidiaryName],
            [
                'parent_ownership_percent' => $parentSharePercent,
                'nci_ownership_percent' => $nciSharePercent,
                'net_income_idr' => $subsidiaryNetIncomeIdr,
                'nci_share_net_income_idr' => $nciIncome,
            ]
        );
    }

    /**
     * 52.7 Audit Grup & Eliminasi
     */
    public function auditIntercompany(): array
    {
        $transactions = IntercompanyTransaction::count();
        $loans = IntercompanyLoan::count();
        $rules = TransferPricingRule::count();
        $eliminations = EliminationEntry::count();

        // Invariant: tidak boleh transaksi dengan entitas yang sama
        $invalidTx = IntercompanyTransaction::whereColumn('selling_entity', 'buying_entity')->count();

        // Invariant: pinjaman repaid <= principal
        $invalidLoans = IntercompanyLoan::whereColumn('repaid_principal_idr', '>', 'principal_idr')->count();

        // Invariant: NCI total ownership = 100%
        $invalidNci = SubsidiaryNci::whereRaw('ABS(parent_ownership_percent + nci_ownership_percent - 100) > 0.01')->count();

        $discrepancyCount = $invalidTx + $invalidLoans + $invalidNci;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'transaction_count' => $transactions,
            'loan_count' => $loans,
            'rule_count' => $rules,
            'elimination_count' => $eliminations,
        ];
    }
}
