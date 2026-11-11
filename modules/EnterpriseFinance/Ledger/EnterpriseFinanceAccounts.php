<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul EnterpriseFinance.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EnterpriseFinanceAccounts
{
    public static function incomeSummary(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:income_summary:{$entity}:{$ccy}";
    }

    public static function retainedEarnings(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:retained_earnings:{$entity}:{$ccy}";
    }

    public static function vatOutputPayable(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:vat_output_payable:{$entity}:{$ccy}";
    }

    public static function vatInputReceivable(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:vat_input_receivable:{$entity}:{$ccy}";
    }

    public static function vatSettlementClearing(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:vat_settlement_clearing:{$entity}:{$ccy}";
    }

    public static function dividendDeclared(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:dividend_declared:{$entity}:{$ccy}";
    }

    public static function dividendPayable(string $entity = 'CORP-HOLDING', string $ccy = 'IDR'): string
    {
        return "ef:dividend_payable:{$entity}:{$ccy}";
    }

    /**
     * Memastikan akun buku besar tersedia secara idempoten.
     */
    public static function ensureAccount(
        string $code,
        string $name,
        AccountKind $kind,
        string $assetCode = 'IDR',
        bool $allowNegative = false
    ): LedgerAccount {
        $existing = LedgerAccount::query()->where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        return LedgerAccount::create([
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'asset_code' => $assetCode,
            'allow_negative' => $allowNegative,
        ]);
    }

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        $entities = ['CORP-HOLDING', 'CORP_HOLDING', 'HOLDING', 'SUB_A', 'SUB_B', 'CORRUPT-ENT', 'default'];
        $defs = [
            [
                'code' => 'ef:holding_dividend_income:IDR',
                'name' => 'Holding Dividend Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ef:holding_dividend_receivable:IDR',
                'name' => 'Holding Dividend Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_facility_commitment:IDR',
                'name' => 'Syndicated Facility Commitment',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_loan_liability:IDR',
                'name' => 'Syndicated Loan Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_interest_expense:IDR',
                'name' => 'Syndicated Interest Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ef:operating_budget_clearing:IDR',
                'name' => 'Operating Budget Clearing',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:cash_disbursement_clearing:IDR',
                'name' => 'Cash Disbursement Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:tax_payable_ppn:IDR',
                'name' => 'PPN Output Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:tax_prepaid_clearing:IDR',
                'name' => 'Prepaid Tax Input Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            // Generic fallback accounts
            [
                'code' => 'ef:income_summary:IDR',
                'name' => 'General Income Summary IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:retained_earnings:IDR',
                'name' => 'General Retained Earnings IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:vat_output_payable:IDR',
                'name' => 'General VAT Output Payable IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:vat_input_receivable:IDR',
                'name' => 'General VAT Input Receivable IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:vat_settlement_clearing:IDR',
                'name' => 'General VAT Settlement Clearing IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:dividend_declared:IDR',
                'name' => 'General Dividend Declared IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:dividend_payable:IDR',
                'name' => 'General Dividend Payable IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];

        foreach ($entities as $entity) {
            $defs[] = [
                'code' => self::incomeSummary($entity),
                'name' => "Income Summary {$entity} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::retainedEarnings($entity),
                'name' => "Retained Earnings {$entity} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::vatOutputPayable($entity),
                'name' => "VAT Output Payable {$entity} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::vatInputReceivable($entity),
                'name' => "VAT Input Receivable {$entity} IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::vatSettlementClearing($entity),
                'name' => "VAT Settlement Clearing {$entity} IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::dividendDeclared($entity),
                'name' => "Dividend Declared {$entity} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
            $defs[] = [
                'code' => self::dividendPayable($entity),
                'name' => "Dividend Payable {$entity} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ];
        }

        return $defs;
    }
}
