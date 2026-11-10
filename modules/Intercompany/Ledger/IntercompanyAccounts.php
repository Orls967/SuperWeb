<?php

declare(strict_types=1);

namespace Modules\Intercompany\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

final class IntercompanyAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        $entities = ['HOLDING', 'SUB_A', 'SUB_B', 'SUB_C', 'default'];
        $currencies = ['IDR', 'USD', 'SGD'];

        $defs = [];

        foreach ($entities as $entity) {
            foreach ($currencies as $ccy) {
                // Akun entitas tunggal
                $defs[] = [
                    'code' => self::sales($entity, $ccy),
                    'name' => "Intercompany Sales {$entity} {$ccy}",
                    'kind' => AccountKind::REVENUE,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::withholdingTaxPayable($entity, $ccy),
                    'name' => "WHT Payable {$entity} {$ccy}",
                    'kind' => AccountKind::LIABILITY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::prepaidTaxPph23($entity, $ccy),
                    'name' => "Prepaid Tax PPh 23 {$entity} {$ccy}",
                    'kind' => AccountKind::ASSET,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::tpAdjustmentExpense($entity, $ccy),
                    'name' => "TP Adjustment Expense {$entity} {$ccy}",
                    'kind' => AccountKind::EXPENSE,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::fixedAssets($entity, $ccy),
                    'name' => "Fixed Assets {$entity} {$ccy}",
                    'kind' => AccountKind::ASSET,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::accumulatedDepreciation($entity, $ccy),
                    'name' => "Accumulated Depr {$entity} {$ccy}",
                    'kind' => AccountKind::CONTRA_ASSET,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::inventory($entity, $ccy),
                    'name' => "Inventory {$entity} {$ccy}",
                    'kind' => AccountKind::INVENTORY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::unrealizedProfitReserve($entity, $ccy),
                    'name' => "Unrealized Profit Reserve {$entity} {$ccy}",
                    'kind' => AccountKind::LIABILITY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::inventoryAdjustment($entity, $ccy),
                    'name' => "Inventory Adj {$entity} {$ccy}",
                    'kind' => AccountKind::EXPENSE,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::fctrReserve($entity, $ccy),
                    'name' => "FCTR Reserve {$entity} {$ccy}",
                    'kind' => AccountKind::LIABILITY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::foreignEquityAdj($entity, $ccy),
                    'name' => "Foreign Equity Adj {$entity} {$ccy}",
                    'kind' => AccountKind::LIABILITY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::nciProfitShare($entity, $ccy),
                    'name' => "NCI Profit Share {$entity} {$ccy}",
                    'kind' => AccountKind::EXPENSE,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::nciEquity($entity, $ccy),
                    'name' => "NCI Equity {$entity} {$ccy}",
                    'kind' => AccountKind::LIABILITY,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];
                $defs[] = [
                    'code' => self::bankClearing($entity, $ccy),
                    'name' => "Bank Clearing {$entity} {$ccy}",
                    'kind' => AccountKind::CLEARING,
                    'asset_code' => $ccy,
                    'allow_negative' => true,
                ];

                // Akun bilateral (pasangan entitas)
                foreach ($entities as $other) {
                    if ($entity === $other) {
                        continue;
                    }

                    $defs[] = [
                        'code' => self::ar($other, $entity, $ccy),
                        'name' => "AR from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::ASSET,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::ap($other, $entity, $ccy),
                        'name' => "AP to {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::AP,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::cogs($other, $entity, $ccy),
                        'name' => "COGS from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::EXPENSE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::loanReceivable($other, $entity, $ccy),
                        'name' => "Loan Rec from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::LOAN_RECEIVABLE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::loanPayable($other, $entity, $ccy),
                        'name' => "Loan Pay to {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::LIABILITY,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::interestIncome($other, $entity, $ccy),
                        'name' => "Interest Inc from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::REVENUE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::interestExpense($other, $entity, $ccy),
                        'name' => "Interest Exp to {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::EXPENSE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::interestReceivable($other, $entity, $ccy),
                        'name' => "Interest Rec from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::ASSET,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::interestPayable($other, $entity, $ccy),
                        'name' => "Interest Pay to {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::LIABILITY,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::costSharingRecovery($entity, $other, $ccy),
                        'name' => "Cost Recovery from {$other} at {$entity} {$ccy}",
                        'kind' => AccountKind::REVENUE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                    $defs[] = [
                        'code' => self::managementFeeExpense($entity, $other, $ccy),
                        'name' => "Management Fee to {$entity} at {$other} {$ccy}",
                        'kind' => AccountKind::EXPENSE,
                        'asset_code' => $ccy,
                        'allow_negative' => true,
                    ];
                }
            }
        }

        return $defs;
    }

    public static function ar(string $buyer, string $seller, string $ccy = 'IDR'): string
    {
        return "ic:ar:{$buyer}:{$seller}:{$ccy}";
    }

    public static function ap(string $seller, string $buyer, string $ccy = 'IDR'): string
    {
        return "ic:ap:{$seller}:{$buyer}:{$ccy}";
    }

    public static function sales(string $seller, string $ccy = 'IDR'): string
    {
        return "ic:sales:{$seller}:{$ccy}";
    }

    public static function cogs(string $seller, string $buyer, string $ccy = 'IDR'): string
    {
        return "ic:cogs:{$seller}:{$buyer}:{$ccy}";
    }

    public static function loanReceivable(string $borrower, string $lender, string $ccy = 'IDR'): string
    {
        return "ic:loan_receivable:{$borrower}:{$lender}:{$ccy}";
    }

    public static function loanPayable(string $lender, string $borrower, string $ccy = 'IDR'): string
    {
        return "ic:loan_payable:{$lender}:{$borrower}:{$ccy}";
    }

    public static function interestIncome(string $borrower, string $lender, string $ccy = 'IDR'): string
    {
        return "ic:interest_income:{$borrower}:{$lender}:{$ccy}";
    }

    public static function interestExpense(string $lender, string $borrower, string $ccy = 'IDR'): string
    {
        return "ic:interest_expense:{$lender}:{$borrower}:{$ccy}";
    }

    public static function interestReceivable(string $borrower, string $lender, string $ccy = 'IDR'): string
    {
        return "ic:interest_receivable:{$borrower}:{$lender}:{$ccy}";
    }

    public static function interestPayable(string $lender, string $borrower, string $ccy = 'IDR'): string
    {
        return "ic:interest_payable:{$lender}:{$borrower}:{$ccy}";
    }

    public static function withholdingTaxPayable(string $borrower, string $ccy = 'IDR'): string
    {
        return "ic:withholding_tax_payable:{$borrower}:{$ccy}";
    }

    public static function prepaidTaxPph23(string $lender, string $ccy = 'IDR'): string
    {
        return "tax:prepaid_pph23:{$lender}:{$ccy}";
    }

    public static function costSharingRecovery(string $provider, string $beneficiary, string $ccy = 'IDR'): string
    {
        return "ic:cost_sharing_recovery:{$provider}:{$beneficiary}:{$ccy}";
    }

    public static function managementFeeExpense(string $provider, string $beneficiary, string $ccy = 'IDR'): string
    {
        return "ic:management_fee_expense:{$provider}:{$beneficiary}:{$ccy}";
    }

    public static function tpAdjustmentExpense(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:tp_adjustment_expense:{$entity}:{$ccy}";
    }

    public static function fixedAssets(string $entity, string $ccy = 'IDR'): string
    {
        return "ast:fixed_assets:{$entity}:{$ccy}";
    }

    public static function accumulatedDepreciation(string $entity, string $ccy = 'IDR'): string
    {
        return "ast:accum_depr:{$entity}:{$ccy}";
    }

    public static function inventory(string $entity, string $ccy = 'IDR'): string
    {
        return "stk:inventory:{$entity}:{$ccy}";
    }

    public static function unrealizedProfitReserve(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:unrealized_profit_reserve:{$entity}:{$ccy}";
    }

    public static function inventoryAdjustment(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:inventory_adj:{$entity}:{$ccy}";
    }

    public static function fctrReserve(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:fctr_reserve:{$entity}:{$ccy}";
    }

    public static function foreignEquityAdj(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:foreign_equity_adj:{$entity}:{$ccy}";
    }

    public static function nciProfitShare(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:nci_profit_share:{$entity}:{$ccy}";
    }

    public static function nciEquity(string $entity, string $ccy = 'IDR'): string
    {
        return "ic:nci_equity:{$entity}:{$ccy}";
    }

    public static function bankClearing(string $entity, string $ccy = 'IDR'): string
    {
        return "trs:bank_clearing:{$entity}:{$ccy}";
    }
}
