<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Helper idempoten akun per-subjek clearing (escrow per L/C, dompet USDT pengguna) — KONSEP §A2.3.
 */
final class ClearingSubjectAccounts
{
    public static function escrow(string $letterOfCreditId): string
    {
        // Akun escrow clearing: boleh negatif sesuai konvensi akun escrow/clearing modul ini (lawan dompet).
        return self::ensure("tf:escrow:{$letterOfCreditId}:USDT", "Escrow USDT L/C {$letterOfCreditId}", AccountKind::ESCROW, true);
    }

    public static function usdtWallet(int $userId): string
    {
        return self::ensure("wallet:user:{$userId}:USDT", "Dompet USDT #{$userId}", AccountKind::WALLET, false);
    }

    private static function ensure(string $code, string $name, AccountKind $kind, bool $allowNegative): string
    {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(), 'asset_code' => 'USDT', 'name' => $name, 'kind' => $kind->value,
                'allow_negative' => $allowNegative, 'cached_balance' => '0', 'is_frozen' => false,
            ]
        );

        return $code;
    }
}
