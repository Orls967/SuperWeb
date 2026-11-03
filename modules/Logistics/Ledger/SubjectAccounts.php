<?php

declare(strict_types=1);

namespace Modules\Logistics\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Helper idempoten akun per-subjek (carrier / supplier sumber) — KONSEP §A2.3.
 * Akun utang (AP) boleh negatif karena posting tahan/lepas dapat mendahului akru.
 */
final class SubjectAccounts
{
    public static function carrierPayable(int $carrierId): string
    {
        return self::ensure("ap:carrier:{$carrierId}:IDR", "AP Carrier {$carrierId}");
    }

    public static function supplierPayable(int $partyId): string
    {
        return self::ensure("ap:supplier:{$partyId}:IDR", "AP Supplier Sumber {$partyId}");
    }

    private static function ensure(string $code, string $name): string
    {
        LedgerAccount::firstOrCreate(
            ['code' => $code, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(), 'name' => $name, 'kind' => AccountKind::AP->value,
                'allow_negative' => true, 'cached_balance' => '0', 'is_frozen' => false,
            ]
        );

        return $code;
    }
}
