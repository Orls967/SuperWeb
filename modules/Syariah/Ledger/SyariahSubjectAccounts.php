<?php

declare(strict_types=1);

namespace Modules\Syariah\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Helper idempoten untuk pembuatan akun per-subjek modul Syariah (KONSEP §A2.2 & §A2.3).
 */
final class SyariahSubjectAccounts
{
    public static function supplierPayable(int $supplierPartyId): string
    {
        return self::ensure(
            code: "ap:syb:supplier:{$supplierPartyId}:IDR",
            name: "Utang Pemasok Murabahah #{$supplierPartyId}",
            kind: AccountKind::AP,
            allowNegative: false
        );
    }

    public static function murabahahReceivable(int $contractId): string
    {
        return self::ensure(
            code: "syb:murabahah_receivable:{$contractId}:IDR",
            name: "Piutang Murabahah Akad #{$contractId}",
            kind: AccountKind::LOAN_RECEIVABLE,
            allowNegative: true
        );
    }

    public static function deferredMargin(int $contractId): string
    {
        return self::ensure(
            code: "syb:deferred_margin:{$contractId}:IDR",
            name: "Margin Murabahah Ditangguhkan Akad #{$contractId}",
            kind: AccountKind::LIABILITY,
            allowNegative: false
        );
    }

    public static function ijarahReceivable(int $facilityId): string
    {
        return self::ensure(
            code: "syb:ijarah_receivable:{$facilityId}:IDR",
            name: "Piutang Sewa Ijarah Fasilitas #{$facilityId}",
            kind: AccountKind::LOAN_RECEIVABLE,
            allowNegative: true
        );
    }

    public static function sukukSpvCash(int $sukukId): string
    {
        return self::ensure(
            code: "syb:sukuk_spv_cash:{$sukukId}:IDR",
            name: "Kas Kustodian SPV Sukuk #{$sukukId}",
            kind: AccountKind::CLEARING,
            allowNegative: true
        );
    }

    public static function customerWallet(int $userId): string
    {
        return self::ensure(
            code: "wallet:user:{$userId}:IDR",
            name: "Dompet IDR Nasabah #{$userId}",
            kind: AccountKind::WALLET,
            allowNegative: true
        );
    }

    private static function ensure(string $code, string $name, AccountKind $kind, bool $allowNegative): string
    {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'asset_code' => 'IDR',
                'name' => $name,
                'kind' => $kind->value,
                'allow_negative' => $allowNegative,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        return $code;
    }
}
