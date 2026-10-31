<?php

declare(strict_types=1);

namespace Modules\Ev\Ledger;

use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Ev (KONSEP §1F).
 */
final class EvAccounts
{
    public const REVENUE = 'oto:ev_charging_revenue:IDR';

    public const ESCROW = 'escrow:payment:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::REVENUE,
                'name' => 'EV Charging Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::ESCROW,
                'name' => 'Payment Escrow Account',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'oto:ev_revenue:IDR',
                'name' => 'Legacy EV Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function ensureUserWallet(Ledger $ledger, int $userId): string
    {
        $code = "wallet:user:{$userId}:IDR";
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "User {$userId} Wallet",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        return $code;
    }
}
