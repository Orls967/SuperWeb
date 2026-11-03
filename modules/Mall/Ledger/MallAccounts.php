<?php

declare(strict_types=1);

namespace Modules\Mall\Ledger;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Mall.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class MallAccounts
{
    public const EXPENSE_POINTS_ISSUED_PTS = 'expense:mall:points_issued:PTS';

    public const REVENUE_POINTS_REDEEMED_PTS = 'revenue:mall:points_redeemed:PTS';

    public const REVENUE_POINTS_BREAKAGE_PTS = 'revenue:mall:points_breakage:PTS';

    public const LIABILITY_TENANT_DEPOSIT = 'liability:mall:tenant_deposit:IDR';

    public const LIABILITY_VOUCHER = 'liability:mall:voucher:IDR';

    public const EXPENSE_LOYALTY = 'expense:mall:loyalty:IDR';

    public const REVENUE_VOUCHER_BREAKAGE = 'revenue:mall:voucher_breakage:IDR';

    public const REVENUE_PARKING = 'revenue:mall:parking:IDR';

    public const CASH_PARKING = 'cash:mall:parking:IDR';

    public const REVENUE_MEMBERSHIP = 'revenue:mall:membership:IDR';

    public const CLEARING_POINTS_BUFFER = 'clearing:mall:points_buffer:IDR';

    public const REVENUE_POINTS_BREAKAGE_IDR = 'revenue:mall:points_breakage:IDR';

    public const LIABILITY_POINTS_RESERVE = 'liability:mall:points_reserve:IDR';

    public const CLEARING_REDEMPTION = 'clearing:mall:redemption:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::EXPENSE_POINTS_ISSUED_PTS,
                'name' => 'Beban Poin Loyalitas Diterbitkan (PTS)',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'PTS',
                'allow_negative' => true,
            ],
            [
                'code' => self::REVENUE_POINTS_REDEEMED_PTS,
                'name' => 'Pendapatan Poin Ditukar Voucher (PTS)',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'PTS',
                'allow_negative' => false,
            ],
            [
                'code' => self::REVENUE_POINTS_BREAKAGE_PTS,
                'name' => 'Pendapatan Poin Kedaluwarsa (Breakage PTS)',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'PTS',
                'allow_negative' => false,
            ],
            [
                'code' => self::REVENUE_PARKING,
                'name' => 'Pendapatan Parkir Duta Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::CASH_PARKING,
                'name' => 'Kas Fisik Gate Parkir Duta Mall',
                'kind' => AccountKind::CASH,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::REVENUE_MEMBERSHIP,
                'name' => 'Pendapatan Keanggotaan Parkir Bulanan',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::CLEARING_POINTS_BUFFER,
                'name' => 'Buffer Poin Loyalitas Mall',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::REVENUE_POINTS_BREAKAGE_IDR,
                'name' => 'Pendapatan Kadaluarsa Poin (Breakage IDR)',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::LIABILITY_POINTS_RESERVE,
                'name' => 'Kewajiban Cadangan Poin Loyalitas Mall',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CLEARING_REDEMPTION,
                'name' => 'Kliring Penukaran Poin Tenant Mall',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::LIABILITY_TENANT_DEPOSIT,
                'name' => 'Titipan Deposit Jaminan Sewa Tenant Mall',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::LIABILITY_VOUCHER,
                'name' => 'Kewajiban Voucher Belanja Mall',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::EXPENSE_LOYALTY,
                'name' => 'Beban Promosi & Loyalitas Mall',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::REVENUE_VOUCHER_BREAKAGE,
                'name' => 'Pendapatan Breakage Voucher Kedaluwarsa',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:rent:IDR',
                'name' => 'Pendapatan Sewa & Bagi Hasil Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:rent_settlement:IDR',
                'name' => 'Penyelesaian Tunggakan Sewa Tenant',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:lease:base_rent:IDR',
                'name' => 'Pendapatan Sewa Pokok Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:service_charge:IDR',
                'name' => 'Pendapatan Service Charge Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:promotion_levy:IDR',
                'name' => 'Pendapatan Dana Promosi Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:foodcourt_revenue_share:IDR',
                'name' => 'Pendapatan Bagi Hasil Foodcourt Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:cinema_revenue_share:IDR',
                'name' => 'Pendapatan Bagi Hasil Bioskop Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:retail_revenue_share:IDR',
                'name' => 'Pendapatan Bagi Hasil Ritel Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:utilities:electricity:IDR',
                'name' => 'Pendapatan Listrik Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:utilities:water:IDR',
                'name' => 'Pendapatan Air Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:utilities:gas:IDR',
                'name' => 'Pendapatan Gas Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:utilities:ac_overtime:IDR',
                'name' => 'Pendapatan Lembur AC Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:repairs:IDR',
                'name' => 'Pendapatan Perbaikan Fasilitas Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'revenue:mall:penalties:IDR',
                'name' => 'Pendapatan Denda Keterlambatan Mall',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function get(string $code): LedgerAccount
    {
        $account = LedgerAccount::where('code', $code)->first();
        if ($account !== null) {
            return $account;
        }

        foreach (self::definitions() as $def) {
            if ($def['code'] === $code) {
                return LedgerAccount::firstOrCreate(
                    ['code' => $code],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => $def['name'],
                        'asset_code' => $def['asset_code'],
                        'kind' => $def['kind']->value ?? (string) $def['kind'],
                        'allow_negative' => $def['allow_negative'],
                        'cached_balance' => '0',
                        'is_frozen' => false,
                    ]
                );
            }
        }

        throw new InvalidArgumentException("Akun ledger {$code} tidak terdaftar di MallAccounts.");
    }

    public static function pointsIssuedAccount(): LedgerAccount
    {
        return self::get(self::EXPENSE_POINTS_ISSUED_PTS);
    }

    public static function pointsRedeemedAccount(): LedgerAccount
    {
        return self::get(self::REVENUE_POINTS_REDEEMED_PTS);
    }

    public static function pointsBreakageAccount(): LedgerAccount
    {
        return self::get(self::REVENUE_POINTS_BREAKAGE_PTS);
    }

    public static function tenantDepositLiabilityAccount(): LedgerAccount
    {
        return self::get(self::LIABILITY_TENANT_DEPOSIT);
    }

    public static function voucherLiabilityAccount(): LedgerAccount
    {
        return self::get(self::LIABILITY_VOUCHER);
    }

    public static function loyaltyExpenseAccount(): LedgerAccount
    {
        return self::get(self::EXPENSE_LOYALTY);
    }

    public static function voucherBreakageRevenueAccount(): LedgerAccount
    {
        return self::get(self::REVENUE_VOUCHER_BREAKAGE);
    }
}
