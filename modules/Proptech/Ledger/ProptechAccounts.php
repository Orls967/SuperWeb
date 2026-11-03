<?php

declare(strict_types=1);

namespace Modules\Proptech\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Proptech.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class ProptechAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'ast:fixed_assets:IDR',
                'name' => 'Fixed Assets Capitalized',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'epc:cip_liability:IDR',
                'name' => 'Construction in Progress CIP',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'escrow:payment:IDR',
                'name' => 'Payment Escrow Central Account',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'prp:flex_escrow:IDR',
                'name' => 'Flex Space Escrow Deposit',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'prp:flex_penalty_rev:IDR',
                'name' => 'Flex Space No-Show Penalty Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'prp:flex_revenue:IDR',
                'name' => 'Flex Space Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'prp:flex_revenue_unearned:IDR',
                'name' => 'Flex Space Unearned Revenue',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'prp:utility_ar:IDR',
                'name' => 'Proptech Utility Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'prp:utility_rev:IDR',
                'name' => 'Proptech Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'mall:utility_revenue:IDR',
                'name' => 'Mall Tenant Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function tenantAr(int|string $tenantId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "ar:mall:tenant:{$tenantId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "Mall Tenant AR #{$tenantId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::ASSET->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
