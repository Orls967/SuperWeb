<?php

declare(strict_types=1);

namespace Modules\Mall\Ledger;

use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Mall.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class MallAccounts
{
    public const LIABILITY_POINTS_PTS = 'liability:mall:points:PTS';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::LIABILITY_POINTS_PTS,
                'name' => 'Kewajiban Poin Loyalitas Duta Mall',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'PTS',
                'allow_negative' => true,
            ],
            [
                'code' => 'revenue:mall:parking:IDR',
                'name' => 'Pendapatan Parkir Duta Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'cash:mall:parking:IDR',
                'name' => 'Kas Fisik Gate Parkir Duta Mall',
                'kind' => AccountKind::CASH,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'revenue:mall:membership:IDR',
                'name' => 'Pendapatan Keanggotaan Parkir Bulanan',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'clearing:mall:points_buffer:IDR',
                'name' => 'Buffer Poin Loyalitas Mall',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'revenue:mall:points_breakage:IDR',
                'name' => 'Pendapatan Kadaluarsa Poin (Breakage)',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'liability:mall:points_reserve:IDR',
                'name' => 'Kewajiban Cadangan Poin Loyalitas Mall',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:mall:redemption:IDR',
                'name' => 'Kliring Penukaran Poin Tenant Mall',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function pointsLiabilityAccount(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => self::LIABILITY_POINTS_PTS],
            [
                'name' => 'Kewajiban Poin Loyalitas Duta Mall',
                'asset_code' => 'PTS',
                'kind' => AccountKind::LIABILITY,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
