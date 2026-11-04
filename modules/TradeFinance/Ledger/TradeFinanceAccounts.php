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
                // Posisi revaluasi kurs IDR/USDT escrow clearing; boleh negatif (lawan akun untung/rugi kurs Treasury).
                'code' => 'tf:fx_position:IDR',
                'name' => 'Clearing USDT FX Revaluation Position',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                // Sumber likuiditas USDT (on-ramp tersimulasi); boleh negatif karena akun clearing sumber dana.
                'code' => 'tf:usdt_liquidity_source:USDT',
                'name' => 'Clearing USDT Liquidity Source (simulated)',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'USDT',
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
