<?php

declare(strict_types=1);

namespace Modules\Port\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan akun standar untuk modul Port & Terminal (Lini 27, D27-4).
 */
final class PortAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'rev:prt:thc:IDR',
                'name' => 'Port Terminal Handling Charge Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rev:prt:dockage:IDR',
                'name' => 'Port Berth Dockage Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'rev:prt:storage:IDR',
                'name' => 'Port Yard Storage Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'prt:dnd_revenue:IDR',
                'name' => 'Port Demurrage and Detention Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function lineReceivableCode(int|string $linePartyId): string
    {
        return "ar:prt:line:{$linePartyId}:IDR";
    }
}
