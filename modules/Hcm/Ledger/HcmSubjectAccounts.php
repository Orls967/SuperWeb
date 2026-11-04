<?php

declare(strict_types=1);

namespace Modules\Hcm\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Helper idempoten untuk akun per-subjek modul HCM (KONSEP §A2.2 & §A2.3).
 */
final class HcmSubjectAccounts
{
    public static function salaryExpense(string $costCenter): string
    {
        return self::ensure(
            code: "expense:hcm:salary:{$costCenter}:IDR",
            name: "Beban Gaji Karyawan CC {$costCenter}",
            kind: AccountKind::EXPENSE,
            allowNegative: true
        );
    }

    public static function employerContributionExpense(string $costCenter): string
    {
        return self::ensure(
            code: "expense:hcm:employer_contribution:{$costCenter}:IDR",
            name: "Beban Iuran Pemberi Kerja CC {$costCenter}",
            kind: AccountKind::EXPENSE,
            allowNegative: true
        );
    }

    public static function salaryPayable(string $employeeId): string
    {
        return self::ensure(
            code: "hcm:salary_payable:{$employeeId}:IDR",
            name: "Utang Gaji Karyawan #{$employeeId}",
            kind: AccountKind::LIABILITY,
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
