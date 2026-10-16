<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul TradeFinance.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class TradeFinanceAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'tf:crossborder_escrow:IDR',
                'name' => 'Cross-Border Escrow Holding',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tf:exporter_settlement:IDR',
                'name' => 'Exporter Settlement Account',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tf:importer_wallet:IDR',
                'name' => 'Importer Wallet Account',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
