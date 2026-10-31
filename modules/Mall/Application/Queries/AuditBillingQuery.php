<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Brick\Math\BigDecimal;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Ledger\MallAccounts;

class AuditBillingQuery
{
    /**
     * Audit kesesuaian antara seluruh data invoice mall, baris rinciannya, dan entri pembukuan double-entry ledger.
     *
     * @return array{
     *     passed: bool,
     *     invoices_checked: int,
     *     total_billed: int,
     *     total_paid: int,
     *     ledger_paid: int,
     *     discrepancies: array<string>
     * }
     */
    public function execute(): array
    {
        $invoices = Invoice::with('lines')->get();
        $discrepancies = [];

        $invoicesChecked = 0;
        $totalBilled = 0;
        $totalPaid = 0;
        $ledgerPaid = 0;

        foreach ($invoices as $invoice) {
            $invoicesChecked++;
            $totalBilled += $invoice->total_amount;
            $totalPaid += $invoice->paid_amount;

            // 1. Audit internal invoice & lines
            $subtotalCalc = 0;
            $penaltyCalc = 0;
            $paidCalc = 0;

            foreach ($invoice->lines as $line) {
                if ($line->type === InvoiceLineType::PENALTY) {
                    $penaltyCalc += $line->amount;
                } else {
                    $subtotalCalc += $line->amount;
                }
                $paidCalc += $line->paid_amount;
            }

            if ($invoice->subtotal !== $subtotalCalc) {
                $discrepancies[] = "Invoice #{$invoice->invoice_number}: Subtotal mismatch (Invoice: {$invoice->subtotal}, Lines: {$subtotalCalc})";
            }

            if ($invoice->penalty_amount !== $penaltyCalc) {
                $discrepancies[] = "Invoice #{$invoice->invoice_number}: Penalty mismatch (Invoice: {$invoice->penalty_amount}, Lines: {$penaltyCalc})";
            }

            if ($invoice->total_amount !== ($subtotalCalc + $penaltyCalc)) {
                $discrepancies[] = "Invoice #{$invoice->invoice_number}: Total amount mismatch (Invoice: {$invoice->total_amount}, Expected: ".($subtotalCalc + $penaltyCalc).')';
            }

            if ($invoice->paid_amount !== $paidCalc) {
                $discrepancies[] = "Invoice #{$invoice->invoice_number}: Paid amount mismatch (Invoice: {$invoice->paid_amount}, Lines paid: {$paidCalc})";
            }

            if ($invoice->status === InvoiceStatus::PAID && $invoice->paid_amount < $invoice->total_amount) {
                $discrepancies[] = "Invoice #{$invoice->invoice_number}: Status is PAID but paid_amount ({$invoice->paid_amount}) < total_amount ({$invoice->total_amount})";
            }

            // 2. Audit invoice paid_amount terhadap entri ledger
            if ($invoice->paid_amount > 0) {
                $txIds = LedgerTransaction::where(function ($q) {
                    $q->where('reference_type', Invoice::class)
                        ->orWhere('reference_type', 'Modules\Mall\Domain\Models\Invoice')
                        ->orWhere('reference_type', 'mall_invoices');
                })
                    ->where('reference_id', $invoice->id)
                    ->pluck('id');

                $mallRevAccountIds = LedgerAccount::where('code', 'like', 'revenue:mall:%')
                    ->pluck('id');

                $ledgerEntries = LedgerEntry::whereIn('transaction_id', $txIds)
                    ->whereIn('account_id', $mallRevAccountIds)
                    ->get();

                $invoiceLedgerSum = 0;
                foreach ($ledgerEntries as $entry) {
                    $amt = BigDecimal::of($entry->amount)->toInt();
                    if ($amt > 0) {
                        $invoiceLedgerSum += $amt;
                    }
                }

                $ledgerPaid += $invoiceLedgerSum;

                if ($invoice->paid_amount !== $invoiceLedgerSum) {
                    $discrepancies[] = "Invoice #{$invoice->invoice_number}: Ledger discrepancy! (Invoice paid: {$invoice->paid_amount}, Ledger posted: {$invoiceLedgerSum})";
                }
            }
        }

        // 3. Audit integritas saldo akun pendapatan mall di ledger
        $mallAccounts = LedgerAccount::where('code', 'like', 'revenue:mall:%')->get();
        foreach ($mallAccounts as $acc) {
            $balance = BigDecimal::of($acc->cached_balance ?: '0');
            if ($balance->isNegative()) {
                $discrepancies[] = "Mall Revenue Account [{$acc->code}] has negative balance: {$balance}";
            }
        }

        // 4. Audit keseimbangan global double-entry untuk IDR
        $globalIdrSum = LedgerEntry::where('asset_code', 'IDR')->sum('amount');
        if (abs((float) $globalIdrSum) > 0.0001) {
            $discrepancies[] = "Global IDR ledger balance is unbalanced! SUM(amount) = {$globalIdrSum}";
        }

        // 5. Audit integritas poin loyalitas Mall (Model C PTS - Task A4f)
        $userPointsAccounts = LedgerAccount::where('code', 'like', 'points:user:%:PTS')->get();
        $totalUserWalletBalance = 0;

        foreach ($userPointsAccounts as $userAcc) {
            $userBalance = BigDecimal::of($userAcc->cached_balance ?: '0')->toInt();
            $totalUserWalletBalance += $userBalance;

            if (preg_match('/^points:user:(\d+):PTS$/', (string) $userAcc->code, $m)) {
                $uid = (int) $m[1];
                $activeLotSum = (int) PointBatch::where('user_id', $uid)
                    ->where('is_expired', false)
                    ->where('points_remaining', '>', 0)
                    ->sum('points_remaining');

                if ($userBalance !== $activeLotSum) {
                    $discrepancies[] = "User #{$uid} Points discrepancy: wallet balance ({$userBalance}) != active lots sum ({$activeLotSum})";
                }

                $member = LoyaltyMember::where('user_id', $uid)->first();
                if ($member !== null) {
                    $cachedPoints = $member->current_points;
                    if ($userBalance !== $cachedPoints) {
                        $discrepancies[] = "User #{$uid} Points cache discrepancy: wallet balance ({$userBalance}) != current_points ({$cachedPoints})";
                    }
                }
            }
        }

        // Global reconciliation: Σ saldo dompet = −saldo issued − saldo redeemed − saldo breakage
        $issuedAcc = LedgerAccount::where('code', MallAccounts::EXPENSE_POINTS_ISSUED_PTS)->first();
        $redeemedAcc = LedgerAccount::where('code', MallAccounts::REVENUE_POINTS_REDEEMED_PTS)->first();
        $breakageAcc = LedgerAccount::where('code', MallAccounts::REVENUE_POINTS_BREAKAGE_PTS)->first();

        $issuedBal = $issuedAcc ? BigDecimal::of($issuedAcc->cached_balance ?: '0')->toInt() : 0;
        $redeemedBal = $redeemedAcc ? BigDecimal::of($redeemedAcc->cached_balance ?: '0')->toInt() : 0;
        $breakageBal = $breakageAcc ? BigDecimal::of($breakageAcc->cached_balance ?: '0')->toInt() : 0;

        $expectedWalletSum = -$issuedBal - $redeemedBal - $breakageBal;
        if ($totalUserWalletBalance !== $expectedWalletSum) {
            $discrepancies[] = "Global PTS reconciliation failed: sum of user wallets ({$totalUserWalletBalance}) != -issued ({$issuedBal}) - redeemed ({$redeemedBal}) - breakage ({$breakageBal}) = expected {$expectedWalletSum}";
        }

        return [
            'passed' => empty($discrepancies),
            'invoices_checked' => $invoicesChecked,
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'ledger_paid' => $ledgerPaid,
            'discrepancies' => $discrepancies,
        ];
    }
}
