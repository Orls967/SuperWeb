<?php

declare(strict_types=1);

namespace App\Quality\Ledger;

use Brick\Math\BigDecimal;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Validates account balances against the single sign convention (KONSEP.md §A2.1).
 *
 * Credit = +, Debit = -
 * Normal credit (balance >= 0): revenue, fee, liability, ap, deposit, escrow, wallet, points, contra_asset, collateral.
 * Normal debit (balance <= 0): asset, cash, inventory, loan_receivable, expense.
 * Free (unconstrained during period): clearing, exchange.
 */
final class LedgerNormalBalanceScanner
{
    public const DEBIT_KINDS = [
        'asset',
        'cash',
        'inventory',
        'loan_receivable',
        'expense',
    ];

    public const CREDIT_KINDS = [
        'revenue',
        'fee',
        'liability',
        'ap',
        'deposit',
        'escrow',
        'wallet',
        'points',
        'contra_asset',
        'collateral',
    ];

    public const FREE_KINDS = [
        'clearing',
        'exchange',
    ];

    /**
     * @param  iterable<LedgerAccount>|null  $accounts
     * @return list<string> List of violation identifiers like "account:{code}:{type}"
     */
    public static function violations(?iterable $accounts = null): array
    {
        $accounts ??= LedgerAccount::query()->get();
        $violations = [];

        foreach ($accounts as $account) {
            $kind = is_object($account->kind) ? $account->kind->value : (string) $account->kind;
            $balance = BigDecimal::of($account->cached_balance ?: '0');

            if (in_array($kind, self::DEBIT_KINDS, true)) {
                if ($balance->isGreaterThan(0)) {
                    $violations[] = "account:{$account->code}:debit_normal_has_credit_balance";
                }
            } elseif (in_array($kind, self::CREDIT_KINDS, true)) {
                if ($balance->isLessThan(0)) {
                    $violations[] = "account:{$account->code}:credit_normal_has_debit_balance";
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
