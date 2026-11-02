<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Ledger;

use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Fleet (KONSEP §1F).
 */
final class FleetAccounts
{
    public const LEASE_REVENUE = 'oto:fleet_lease_revenue:IDR';

    public const SLA_CREDIT_EXPENSE = 'expense:oto:fleet_sla_credit:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::LEASE_REVENUE,
                'name' => 'Fleet Lease Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::SLA_CREDIT_EXPENSE,
                'name' => 'Fleet SLA Credit Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:oto:fleet:default:IDR',
                'name' => 'AR Fleet Leasing Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:oto:fleet:9001:IDR',
                'name' => 'AR Fleet Leasing Party 9001',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:fleet:party:default:IDR',
                'name' => 'Legacy AR Fleet Leasing Party Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:fleet:party:501:IDR',
                'name' => 'Legacy AR Fleet Leasing Party 501',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:fleet:sla_penalties:IDR',
                'name' => 'Legacy Fleet SLA Penalties Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function ensurePartyAr(int $partyId): string
    {
        $code = "ar:oto:fleet:{$partyId}:IDR";
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "AR Fleet Leasing Party {$partyId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        return $code;
    }
}
