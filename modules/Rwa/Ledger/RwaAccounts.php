<?php

declare(strict_types=1);

namespace Modules\Rwa\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Rwa.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class RwaAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'rwa:rounding_reserve:IDR',
                'name' => 'RWA Rounding Reserve',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:platform_fees:IDR',
                'name' => 'RWA Platform Fees',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:pool:default:IDR',
                'name' => 'RWA Revenue Pool Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:pool:RWA-MALL01:IDR',
                'name' => 'RWA Revenue Pool Mall 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:pool:RWA-OB01:IDR',
                'name' => 'RWA Revenue Pool Orderbook 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:pool:RWA-AUDIT01:IDR',
                'name' => 'RWA Revenue Pool Audit 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:issued_supply:default',
                'name' => 'RWA Issued Supply Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rwa:unsold:default',
                'name' => 'RWA Unsold Inventory Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
