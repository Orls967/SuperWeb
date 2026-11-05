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
            [
                'code' => 'syb:cash_pool:IDR',
                'name' => 'Syariah Microfinance & BMT Operational Cash Pool',
                'kind' => AccountKind::ASSET,
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
            // G7-D10 & G7-D13 Microfinance, BMT & Agri (Fase 163)
            [
                'code' => 'syb:customer_savings_wadiah:IDR',
                'name' => 'Syariah Customer BMT Savings Wadiah',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:customer_savings_mudharabah:IDR',
                'name' => 'Syariah Customer BMT Savings Mudharabah',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:micro_financing_receivable:IDR',
                'name' => 'Syariah Micro Financing Receivables Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:agri_financing_receivable:IDR',
                'name' => 'Syariah Agri Salam Financing Receivables Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:qardh_receivable:IDR',
                'name' => 'Syariah Qardh al-Hasan Benevolent Receivables Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:micro_ujrah_income:IDR',
                'name' => 'Syariah Micro Ujrah Realized Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:mudharabah_profit_share_income:IDR',
                'name' => 'Syariah Mudharabah Profit Share Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:tazir_social_fund:IDR',
                'name' => 'Syariah Micro Tazir Social Benevolent Fund',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:takaful_claim_offset_clearing:IDR',
                'name' => 'Syariah Takaful Agri Claim Offset Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            // G7-D14..G7-D18 Islamic Trade Finance (Fase 164)
            [
                'code' => 'syb:trade_lc_liability:IDR',
                'name' => 'Syariah Trade LC Documentary Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:trade_lc_margin_deposit:IDR',
                'name' => 'Syariah Trade LC Margin Cash Deposit',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:istisna_work_in_progress:IDR',
                'name' => 'Syariah Istisna Work In Progress Manufacturing Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:istisna_contract_revenue:IDR',
                'name' => 'Syariah Istisna Recognized Contract Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:salam_financing_advance:IDR',
                'name' => 'Syariah Salam Financing Advance Payment Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:salam_inventory:IDR',
                'name' => 'Syariah Salam Delivered Commodity Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:salam_hedging_reserve:IDR',
                'name' => 'Syariah Salam Commodity Hedging Reserve Equity',
                'kind' => AccountKind::EQUITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:scf_murabahah_receivable:IDR',
                'name' => 'Syariah SCF Murabahah Financing Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:scf_inventory:IDR',
                'name' => 'Syariah SCF Acquired Goods Inventory',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:scf_margin_income:IDR',
                'name' => 'Syariah SCF Murabahah Margin Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:tawarruq_broker_payable:IDR',
                'name' => 'Syariah Tawarruq Exchange Broker Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:tawarruq_commodity_holding:IDR',
                'name' => 'Syariah Tawarruq Commodity Temporary Holding Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'syb:tawarruq_fx_clearing:IDR',
                'name' => 'Syariah Tawarruq FX Conversion Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'syb:trade_ujrah_fee_income:IDR',
                'name' => 'Syariah Trade Wakalah & Documentary Ujrah Income',
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
