<?php

declare(strict_types=1);

namespace Modules\Syariah\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Syariah (KONSEP Lini 19, §A2.3).
 * D19-1: Larangan keras akun bunga (interest/bunga) pada transaksi syariah.
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
                // Persediaan barang murabahah saat dibeli bank dari pemasok sebelum akad jual ditandatangani (debit normal <= 0).
                'code' => 'syb:murabahah_asset:IDR',
                'name' => 'Syariah Murabahah Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                // Pendapatan margin murabahah yang telah diakui proporsional kas diterima (kredit normal >= 0).
                'code' => 'syb:margin_income:IDR',
                'name' => 'Syariah Murabahah Realized Margin Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                // Dana kebajikan (Qardhul Hasan) penampung denda keterlambatan ta'zir, bukan pendapatan bank (kredit normal >= 0).
                'code' => 'syb:qardhul_hasan_fund:IDR',
                'name' => 'Syariah Qardhul Hasan Benevolent Fund (Tazir)',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                // Sumber likuiditas simulasi untuk pengisian dompet IDR nasabah pada simulasi/seeder (clearing bebas).
                'code' => 'syb:wadiah_liquidity_source:IDR',
                'name' => 'Syariah Wadiah Liquidity Clearing Source (Simulated)',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
