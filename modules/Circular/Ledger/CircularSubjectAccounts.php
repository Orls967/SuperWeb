<?php

declare(strict_types=1);

namespace Modules\Circular\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Helper idempoten akun per-subjek untuk modul Circular Marketplace (D24-4).
 */
final class CircularSubjectAccounts
{
    public static function userWallet(int $userId): string
    {
        return self::ensure(
            "wallet:user:{$userId}:IDR",
            "User Wallet {$userId}",
            AccountKind::LIABILITY->value
        );
    }

    public static function sellerPayable(int $partyId): string
    {
        return self::ensure(
            "ap:cir:seller:{$partyId}:IDR",
            "AP Circular Seller {$partyId}",
            AccountKind::AP->value
        );
    }

    private static function ensure(string $code, string $name, string $kind): string
    {
        LedgerAccount::firstOrCreate(
            ['code' => $code, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        return $code;
    }
}
