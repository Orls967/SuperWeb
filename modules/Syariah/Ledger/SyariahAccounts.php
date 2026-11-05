<?php

declare(strict_types=1);

namespace Modules\Syariah\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Syariah (KONSEP Lini 19, §A2.3).
 * D19-1 & G7-D5: Larangan keras akun bunga (interest/bunga) pada transaksi syariah.
 * D162-6: Akun Sukuk, Ijarah, Wealth, Zakat, Wakaf, dan Kebajikan.
 */
final class SyariahAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                // Persediaan barang murabahah saat dibeli bank dari pemasok sebelum akad jual ditandatangani.
                'code' => 'syb:murabahah_asset:IDR',
                'name' => 'Syariah Murabahah Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                // Pendapatan margin murabahah yang telah diakui proporsional kas diterima.
                'code' => 'syb:margin_income:IDR',
                'name' => 'Syariah Murabahah Realized Margin Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                // Dana kebajikan (Qardhul Hasan) penampung denda keterlambatan ta'zir, bukan pendapatan bank.
                'code' => 'syb:qardhul_hasan_fund:IDR',
                'name' => 'Syariah Qardhul Hasan Benevolent Fund (Tazir)',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                // Sumber likuiditas simulasi untuk pengisian dompet IDR nasabah pada simulasi/seeder.
                'code' => 'syb:wadiah_liquidity_source:IDR',
                'name' => 'Syariah Wadiah Liquidity Clearing Source (Simulated)',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            // D162-6 Ijarah & IMBT
            [
                'code' => 'syb:ijarah_income:IDR',
                'name' => 'Syariah Ijarah Rental Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:ijarah_depreciation_expense:IDR',
                'name' => 'Syariah Ijarah Depreciation Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:ijarah_accum_depreciation:IDR',
                'name' => 'Syariah Ijarah Accumulated Depreciation',
                'kind' => AccountKind::CONTRA_ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            // D162-6 Sukuk SPV
            [
                'code' => 'syb:sukuk_spv_cash:IDR',
                'name' => 'Syariah Sukuk SPV Custody Cash',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            // D162-6 Zakat, Wakaf & Charity (Amanah - bukan pendapatan)
            [
                'code' => 'syb:zakat_fund:IDR',
                'name' => 'Syariah Zakat Amanah Fund',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:charity_fund:IDR',
                'name' => 'Syariah Benevolent Charity Fund (Purification & Tazir)',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:waqf_principal:IDR',
                'name' => 'Syariah Perpetual Waqf Principal Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:ujrah_income:IDR',
                'name' => 'Syariah Ujrah Service Fee Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function ensureAccountsExist(): void
    {
        foreach (self::definitions() as $def) {
            LedgerAccount::firstOrCreate(
                ['code' => $def['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'asset_code' => $def['asset_code'],
                    'name' => $def['name'],
                    'kind' => $def['kind']->value,
                    'allow_negative' => $def['allow_negative'],
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }
}
