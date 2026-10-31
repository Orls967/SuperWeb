<?php

declare(strict_types=1);

namespace Modules\Epc\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul EPC.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EpcAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'asset:epc:construction_in_progress:IDR',
                'name' => 'EPC Construction in Progress Asset (CIP)',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ast:cip:default:IDR',
                'name' => 'Construction in Progress Default Project',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:epc:contractor_retention:IDR',
                'name' => 'EPC Contractor Retention Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:epc:contractor_payable:IDR',
                'name' => 'EPC Contractor Progress Claim Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'asset:fixed:capitalized_buildings:IDR',
                'name' => 'Fixed Asset Capitalized Buildings',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ast:fixed_assets:IDR',
                'name' => 'Fixed Assets Capitalized',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'epc:retention_payable:default:IDR',
                'name' => 'EPC Retention Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:epc:contractor:default:IDR',
                'name' => 'EPC Contractor Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function cip(string $projectId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "ast:cip:{$projectId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "CIP Project #{$projectId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::ASSET->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function contractorPayable(string $contractorId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "ap:epc:contractor:{$contractorId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "EPC Contractor Payable #{$contractorId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function retentionPayable(string $projectId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => "epc:retention_payable:{$projectId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "EPC Retention Payable #{$projectId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
