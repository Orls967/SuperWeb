<?php

declare(strict_types=1);

namespace Modules\Rwa\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Rwa.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 * Mematuhi U-3: allow_negative = false kecuali clearing/escrow.
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
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:dividend_source:default:IDR',
                'name' => 'RWA Dividend Source Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:dividend_dust_reserve:default:IDR',
                'name' => 'RWA Dividend Dust Reserve Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:platform_fees:IDR',
                'name' => 'RWA Platform Fees',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:pool:default:IDR',
                'name' => 'RWA Revenue Pool Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:pool:RWA-MALL01:IDR',
                'name' => 'RWA Revenue Pool Mall 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:pool:RWA-OB01:IDR',
                'name' => 'RWA Revenue Pool Orderbook 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:pool:RWA-AUDIT01:IDR',
                'name' => 'RWA Revenue Pool Audit 01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:issued_supply:default',
                'name' => 'RWA Issued Supply Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true, // Issuance control account tracks authorized supply as credit balance
            ],
            [
                'code' => 'rwa:unsold:default',
                'name' => 'RWA Unsold Inventory Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'rwa:offering_escrow:default:IDR',
                'name' => 'RWA Offering Escrow Clearing Default',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true, // Escrow clearing allows negative for settlement transit
            ],
            [
                'code' => 'rwa:sale_proceeds:default:IDR',
                'name' => 'RWA Issuer Sale Proceeds Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function offeringEscrow(int|string $offeringId = 'default'): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "rwa:offering_escrow:{$offeringId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "RWA Offering Escrow Clearing #{$offeringId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::CLEARING->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function saleProceeds(int|string $assetId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "rwa:sale_proceeds:{$assetId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "RWA Sale Proceeds Asset #{$assetId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function revenuePool(string $symbol): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "rwa:pool:{$symbol}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "RWA Revenue Pool {$symbol}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function platformFees(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'rwa:platform_fees:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'RWA Platform Fees',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
