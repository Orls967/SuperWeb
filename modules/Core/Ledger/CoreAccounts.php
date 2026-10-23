<?php

declare(strict_types=1);

namespace Modules\Core\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Core.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class CoreAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'clearing:external:IDR',
                'name' => 'Rekening Kliring Eksternal IDR',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:golden_scenario:IDR',
                'name' => 'Golden Scenario Clearing Account',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'escrow:medical_tourism:IDR',
                'name' => 'Medical Tourism Escrow Account',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:billing_clearing:IDR',
                'name' => 'Hospital Billing Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:city_ledger_receivable:IDR',
                'name' => 'Hotel City Ledger Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:settlement_clearing:IDR',
                'name' => 'Hotel Settlement Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'reserve:insurance_pool:IDR',
                'name' => 'Insurance Pool Reserve',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'revenue:group_conglomerate:IDR',
                'name' => 'Group Conglomerate Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:pos_revenue:IDR',
                'name' => 'Venue POS Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'claimant:payable:default:IDR',
                'name' => 'Claimant Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'claimant:payable:CL-LOGISTICS-01:IDR',
                'name' => 'Claimant Payable Logistics 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'claimant:payable:CL-MINING-02:IDR',
                'name' => 'Claimant Payable Mining 02',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'claimant:payable:CL-HOTEL-03:IDR',
                'name' => 'Claimant Payable Hotel 03',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:stablecoin:default:USDC',
                'name' => 'Stablecoin Clearing Default',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'USDC',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:stablecoin:MINE_MOROWALI:USDC',
                'name' => 'Stablecoin Clearing Mine Morowali',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'USDC',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:stablecoin:SMELTER_SINGAPORE:USDC',
                'name' => 'Stablecoin Clearing Smelter Singapore',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'USDC',
                'allow_negative' => true,
            ],
            [
                'code' => 'asset:test_cash:IDR',
                'name' => 'Operational Cash IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'intercompany:payable:default:IDR',
                'name' => 'Intercompany Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'intercompany:receivable:default:IDR',
                'name' => 'Intercompany Receivable Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'platform:service_fee:IDR',
                'name' => 'Platform Service Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'lending:loan_receivable:default:IDR',
                'name' => 'Lending Loan Receivable Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'lending:disbursement_escrow:IDR',
                'name' => 'Lending Disbursement Escrow',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
