<?php

declare(strict_types=1);

namespace Modules\Treasury\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Treasury.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class TreasuryAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            // Akun Warisan / Kompatibilitas
            [
                'code' => 'TRS_USD_SRC',
                'name' => 'USD Source Account',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => true,
            ],
            [
                'code' => 'TRS_USD_DEST',
                'name' => 'USD Destination Account',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => true,
            ],
            [
                'code' => 'treasury:yield_pool:IDR',
                'name' => 'Central Treasury Yield Pool IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:fx_gain_loss:IDR',
                'name' => 'Treasury FX Gain/Loss IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'treasury:intercompany_sweep:default:IDR',
                'name' => 'Treasury Intercompany Sweep Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],

            // Akun Standar Fase 48 (Tabel D48-10)
            [
                'code' => 'trs:fx_reval_asset_adj:IDR',
                'name' => 'Penyesuaian Revaluasi Aset Valas IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:fx_unrealized_gain_loss:IDR',
                'name' => 'Untung/Rugi Selisih Kurs Belum Terealisasi IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:fx_reval_liability_adj:IDR',
                'name' => 'Penyesuaian Revaluasi Liabilitas Valas IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:fx_settlement_clearing:IDR',
                'name' => 'Kliring Penyelesaian Valas IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:fx_realized_gain_loss:IDR',
                'name' => 'Untung/Rugi Selisih Kurs Terealisasi IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:derivative_asset:IDR',
                'name' => 'Aset Derivatif Forward Contract IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:derivative_liability:IDR',
                'name' => 'Liabilitas Derivatif Forward Contract IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:derivative_gain_loss:IDR',
                'name' => 'Untung/Rugi Derivatif Lindung Nilai IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_clearing:IDR',
                'name' => 'Kliring Perantara Bank IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:hedge_cancellation_cost:IDR',
                'name' => 'Beban Biaya Pembatalan Lindung Nilai IDR',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:bank_reconciliation_diff:IDR',
                'name' => 'Selisih Rekonsiliasi Bank Disetujui IDR',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:facility_payable:IDR',
                'name' => 'Utang Pokok Fasilitas Kredit Bank IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:interest_expense:IDR',
                'name' => 'Beban Bunga Pinjaman Bank IDR',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:interest_payable:IDR',
                'name' => 'Utang Bunga Pinjaman Bank IDR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:pool_sweep_clearing:default:IDR',
                'name' => 'Kliring Sweep Pooling Kas Default IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],

            // Kas Operasional & Kas Kecil
            [
                'code' => 'trs:cash_operational:IDR',
                'name' => 'Kas Operasional Treasury IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:petty_cash:IDR',
                'name' => 'Kas Kecil Imprest IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:petty_cash_expense:IDR',
                'name' => 'Beban Kas Kecil IDR',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],

            // Bank Operasional Multi-Entitas & Multi-Valas
            [
                'code' => 'trs:bank_operational:HOLDING:IDR',
                'name' => 'Bank Operasional Holding IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:HOLDING:USD',
                'name' => 'Bank Operasional Holding USD',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:HOLDING:SGD',
                'name' => 'Bank Operasional Holding SGD',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'SGD',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:HOLDING:EUR',
                'name' => 'Bank Operasional Holding EUR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'EUR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:SUB_A:IDR',
                'name' => 'Bank Operasional Sub A IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:SUB_B:IDR',
                'name' => 'Bank Operasional Sub B IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:bank_operational:SUB_C:IDR',
                'name' => 'Bank Operasional Sub C IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:pool_sweep_clearing:HOLDING:IDR',
                'name' => 'Kliring Sweep Holding IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:pool_sweep_clearing:SUB_A:IDR',
                'name' => 'Kliring Sweep Sub A IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:pool_sweep_clearing:SUB_B:IDR',
                'name' => 'Kliring Sweep Sub B IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:pool_sweep_clearing:SUB_C:IDR',
                'name' => 'Kliring Sweep Sub C IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'trs:intercompany_clearing:IDR',
                'name' => 'Kliring Antar-Perusahaan Treasury IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],

            // Pos Moneter Valas
            [
                'code' => 'trs:fx_monetary_asset:USD',
                'name' => 'Pos Aset Moneter Valas USD',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:fx_monetary_asset:SGD',
                'name' => 'Pos Aset Moneter Valas SGD',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'SGD',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:fx_monetary_asset:EUR',
                'name' => 'Pos Aset Moneter Valas EUR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'EUR',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:fx_monetary_liability:USD',
                'name' => 'Pos Liabilitas Moneter Valas USD',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'USD',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:fx_monetary_liability:SGD',
                'name' => 'Pos Liabilitas Moneter Valas SGD',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'SGD',
                'allow_negative' => false,
            ],
            [
                'code' => 'trs:fx_monetary_liability:EUR',
                'name' => 'Pos Liabilitas Moneter Valas EUR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'EUR',
                'allow_negative' => false,
            ],
        ];
    }
}
