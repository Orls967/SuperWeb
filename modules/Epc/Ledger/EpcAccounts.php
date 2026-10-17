<?php

declare(strict_types=1);

namespace Modules\Epc\Ledger;

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
        ];
    }
}
